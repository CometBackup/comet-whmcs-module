<?php

use WHMCS\Database\Capsule;

require_once __DIR__ . '/functions.php';

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

function cometbackup_MetaData()
{
    return [
        'DisplayName' => 'Comet Backup',
        'APIVersion' => '1.1', // Use API Version 1.1
        'RequiresServer' => true, // Set true if module requires a server to work
    ];
}

function cometbackup_ConfigOptions($params)
{
    return [
        'PolicyGroupGUID'       => [
            'FriendlyName'          => 'Policy Group',
            'Type'                  => 'dropdown',
            'Description'           => '<br>Select a policy group to apply to users of this product',
            'Loader'                => 'cometbackup_ConfigOptionsPolicyGroupLoader',
            'SimpleMode'            => true
        ],
        'StorageProviderID'     => [
            'FriendlyName'          => 'Storage Vault',
            'Type'                  => 'dropdown',
            'Description'           => '<br>Request an initial storage vault for new users',
            'Loader'                => 'cometbackup_ConfigOptionsStorageProvidersLoader',
            'SimpleMode'            => true
        ],
        'Message'               => [
            'FriendlyName'          => 'Note',
            'Description'           => 'The [Create New Policy Group] option will cause a new policy group to be created - for technical reasons, this one-time action is postponed to the first time a new account is created using this product.',
            'SimpleMode'            => true
        ],
    ];
}

function cometbackup_ConfigOptionsPolicyGroupLoader(array $params)
{
    $policyGroups = performAPIRequest($params, [], 'policies/list');

    $maxPolicyNum = 0;
    foreach ($policyGroups as $policyID => $policyName) {
        if (strpos($policyID, 'WHMCS_') === 0) {
            $policyNum = intval(substr($policyID, 6));
            if ($policyNum > $maxPolicyNum) {
                $maxPolicyNum = $policyNum;
            }
        }
    }

    if (array_key_exists('curlerror', $policyGroups))
        throw new Exception('Invalid request. Server mis-configured?');

    $newPolicyGroupID = 'WHMCS_' . ($maxPolicyNum + 1);

    return ['' => 'None'] + $policyGroups + [$newPolicyGroupID => '[Create New Policy Group] (' . $newPolicyGroupID . ')'];
}

function cometbackup_ConfigOptionsStorageProvidersLoader(array $params)
{
    $storageProviders = performAPIRequest($params, [], 'request-storage-vault-providers');

    if (array_key_exists('curlerror', $storageProviders)) {
        throw new Exception('Invalid request. Server mis-configured?');
    }

    return ["" => "None"] + $storageProviders;
}

function cometbackup_CreateAccount(array $params)
{
    // Try a few different options for automatic username selection
    if (!empty($params['username'])) {
        $username = $params['username'];
    } else if (
        !empty($email = $params['clientsdetails']['email']) &&
        count($emailComponents = explode('@', $email)) &&
        $emailComponents[0] !== ""
    ) {
        $username = $emailComponents[0];
    } else if (!empty(strtolower($params['clientsdetails']['firstname']))) {
        $username = strtolower($params['clientsdetails']['firstname'] . $params['serviceid']);
    } else {
        $username = 'user';
    }

    // Make sure username is of sufficient length
    $usernameLength = strlen($username);
    if ($usernameLength < 6) { // Minimum username length is 6 characters
        $randomData = strval(rand(100000, getrandmax())); // Need to supplement with up to 6 random numbers
        $username = $username . substr($randomData, 0, 6 - $usernameLength);
    }

    // Prepare base API request params
    $baseRequestData = [
        'TargetUser' => $username,
    ];

    // Check if username is already in use
    $alreadyExists = true;
    $newUsername = $username;
    while ($alreadyExists === true) {
        $usernameExistsCheck = performAPIRequest($params, $baseRequestData, 'get-user-profile');
        $alreadyExists = array_key_exists('Username', $usernameExistsCheck);

        // If username is already in use, supplement with random data and try again
        if ($alreadyExists) {
            $newUsername = $username . '_' . strval(rand(1000, 9999)); // Supplement with 4 random numbers
            $baseRequestData['TargetUser'] = $newUsername;
        }
    }
    $username = $newUsername;
    $params['username'] = $username;

    // Create policy if it doesn't yet exist
    if (!empty($params['configoption1'])) {
        maybeCreatePolicyGroup($params, $params['configoption1']);
    }

    // Prepare add-user API request params
    $addUserRequestQuery = $baseRequestData + [
        'TargetPassword' => $params['password'],
        'StoreRecoveryCode' => 1
    ];

    $response = performAPIRequest($params, $addUserRequestQuery, 'add-user');

    // Account creation succeeded
    if (isset($response['Status']) && $response['Status'] == 200) {

        // Update username on record in case this changed
        Capsule::table('tblhosting')->where('id', $params['serviceid'])->update([
            'username' => $username
        ]);

        // Request storage vault
        if (!empty($params['configoption2'])) {
            $requestStorageVaultRequestData = $baseRequestData + [
                'StorageProvider' => $params['configoption2']
            ];
            performAPIRequest($params, $requestStorageVaultRequestData, 'request-storage-vault');
        }

        return applyRestrictions($params);
    } else {
        // Account creation failed
        return handleErrorResponse($response);
    }
}

function cometbackup_SuspendAccount(array $params)
{
    return modifyAccountSuspensionState($params, true);
}

function cometbackup_UnsuspendAccount(array $params)
{
    return modifyAccountSuspensionState($params, false);
}

function cometbackup_TerminateAccount(array $params)
{
    $requestData = [
        'TargetUser' => $params['username']
    ];
    $response = performAPIRequest($params, $requestData, 'delete-user');

    if (array_key_exists('Status', $response) && $response['Status'] === 200) {
        return 'success';
    } else if (array_key_exists('Message', $response)) {
        return $response['Message'];
    } else {
        return 'Unknown error - please contact support: ' . base64_encode($response);
    }
}

function cometbackup_ChangePassword(array $params)
{
    $requestData = [
        'TargetUser'        => $params['username'],
        'NewPassword'       => $params['password']
    ];

    $response = performAPIRequest($params, $requestData, 'reset-user-password');

    if (array_key_exists('Status', $response) && $response['Status'] === 200) {
        return 'success';
    } else {
        return handleErrorResponse($response);
    }
}

function cometbackup_ClientArea(array $params)
{
    // Handle client download request
    if (!!$_REQUEST['type'] && strpos($_REQUEST['type'], 'downloadResponse') !== false) {
        switch ($_REQUEST['type']) {
            case 'downloadResponseLinux':
                $fileName = 'ClientInstaller.run';
                $generateClientApiPath = 'linuxgeneric';
                break;
            case 'downloadResponseMacOSX86':
                $fileName = 'ClientInstaller.pkg';
                $generateClientApiPath = 'macos-x86_64';
                break;
            case 'downloadResponseWindowsX86_32Zip':
                $fileName = 'ClientInstaller(32-bit).zip';
                $generateClientApiPath = 'windows-x86_32-zip';
                break;
            case 'downloadResponseWindowsX86_64Zip':
                $fileName = 'ClientInstaller(64-bit).zip';
                $generateClientApiPath = 'windows-x86_64-zip';
                break;
            case 'downloadResponseWindowsAnyCPUZip':
            default:
                $fileName = 'ClientInstaller(AnyCPU).zip';
                $generateClientApiPath = 'windows-anycpu-zip';
        }

        header("Content-type:application/x-octet-stream");
        header("Content-Disposition:attachment;filename=\"" . $fileName . "\"");
        echo softwareDownload(
            $params,
            ['SelfAddress' => getHost($params)],
            'branding/generate-client/' . $generateClientApiPath
        );
        exit(); // Exit here to prevent any other data being added to the stream


        // Handle regular client area page request
    } else {
        $userProfile = performAPIRequest(
            $params,
            ['TargetUser' => $params['username']],
            'get-user-profile-and-hash'
        );

        if (array_key_exists('ProfileHash', $userProfile)) {
            $getJobsForUser = performAPIRequest(
                $params,
                [
                    'Query' => '
                        {
                            "ClauseType": "and",
                            "ClauseChildren": [
                                {
                                    "ClauseType": "",
                                    "RuleField": "BackupJobDetail.Username",
                                    "RuleOperator": "str_eq",
                                    "RuleValue": "' . $params['username'] . '"
                                },
                                {
                                    "ClauseType": "",
                                    "RuleField": "BackupJobDetail.StartTime",
                                    "RuleOperator": "int_gt",
                                    "RuleValue": "' . strval(strtotime("-2 week")) . '"
                                }
                            ]
                        }
                    '
                ],
                'get-jobs-for-custom-search'
            );

            // Expand and format job details for human-readability
            foreach ($getJobsForUser as $key => &$job) {
                // Ignore jobs for removed items
                if (!array_key_exists($job['SourceGUID'], $userProfile['Profile']['Sources'])) {
                    unset($getJobsForUser[$key]);
                    continue;
                }

                if (
                    !array_key_exists('Devices', $userProfile['Profile']) ||
                    !array_key_exists($job['DeviceID'], $userProfile['Profile']['Devices'])
                ) {
                    $job['DeviceName'] = 'Unknown';
                } else {
                    $job['DeviceName'] = $userProfile['Profile']['Devices'][$job['DeviceID']]['FriendlyName'];
                }

                $job['SourceDescription'] = $userProfile['Profile']['Sources'][$job['SourceGUID']]['Description'];
                $job['Status'] = formatStatusType($job['Status']);
                $job['Classification'] = formatJobType($job['Classification']);
                $job['TotalSize'] = formatBytes($job['TotalSize']);
                $job['UploadSize'] = formatBytes($job['UploadSize']);
                $job['DownloadSize'] = formatBytes($job['DownloadSize']);
                $job['StartTime'] = date("Y-m-d h:i", $job['StartTime']);
            }

            // Calculate data usage across all protected items
            $totalSize = 0;
            foreach ($userProfile['Profile']['Sources'] as $source) {
                $totalSize += $source['Statistics']['LastBackupJob']['TotalSize'];
            }

            $templateVars = [
                'Username' => $userProfile['Profile']['Username'],
                'AllProtectedItemsQuota' => ($userProfile['Profile']['AllProtectedItemsQuotaBytes'] / pow(1024, 3)), // Bytes / GiB
                'MaximumDevices' => $userProfile['Profile']['MaximumDevices'],
                'CreateTime' => date("Y-m-d h:i:sa", $userProfile['Profile']['CreateTime']),
                'getJobsForUser' => $getJobsForUser,
                'userProfile' => $userProfile,
                'totalSize' => formatBytes($totalSize),
            ];

            if (!empty($userProfile['Profile']['Destinations'])) {
                $destination = array_values($userProfile['Profile']['Destinations'])[0];
                if ($destination['StorageLimitEnabled'] === true) {
                    $templateVars['StorageVaultQuota'] = $destination['StorageLimitBytes'] / pow(1024, 3); // Bytes / GiB
                }
            } else {
                $templateVars['StorageVaultQuota'] = false;
            }

            // Return template data
            return [
                'templatefile' => 'clientarea',
                'vars' => $templateVars
            ];
        } else if (array_key_exists('Status', $userProfile) && $userProfile['Status'] === 500 && array_key_exists('Message', $userProfile)) {
            return ('Error - please contact support: <span style="color:#A22;word-break:break-word;">' . $userProfile['Message'] . '</span><br>' .
                '<span style="color:#A22;">Error data:</span> <span style="color:#CCC;word-break:break-word;">' .
                base64_encode('TargetUser: ' . var_export($params['username'], true)) .
                '</span>');
        } else {
            return ('Unknown error - please contact support. <br>' .
                '<span style="color:#A22;">Error data:</span> <span style="color:#CCC;word-break:break-word;">' .
                base64_encode(
                    var_export($userProfile, true) .
                        'TargetUser: ' . var_export($params['username'], true)
                ) .
                '</span>');
        }
    }
}

function cometbackup_TestConnection(array $params)
{
    $resp = performAPIRequest($params, [], 'meta/version');

    if (array_key_exists('Version', $resp)) { // Expected Success Response
        $success = 'Server connection test success.';
        $error = false;
        
    } else if (array_key_exists('Status', $resp) && array_key_exists('Message', $resp) && $resp['Status'] == 403) { // Failed Authentication Response
        $success = false;
        $error = $resp['Message'];
        
    } else if (array_key_exists('curlerror', $resp)) { // Failed Connection Response
        $success = false;
        $error = $resp['curlerror'];
        
    } else if (is_array($resp) && count($resp) === 0) { // No Valid Listener Response
        $success = false;
        $error = 'Empty response from server: No service listening on this port?';
        
    } else if ($resp === NULL) { // Invalid Response
        $success = false;
        $error = 'Invalid response.';
        
    } else { // Unknown Bad Response
        $success = false;
        $keys = array_keys($resp);
        if (count($keys) === 1 && strlen($resp[$keys[0]]) > 0) {
            $error = $resp[$keys[0]];
        } else {
            $error = 'Unknown error - please contact support: ' . base64_encode(var_export($resp, true));
        }
    }

    return [
        'success' => $success,
        'error' => $error
    ];
}

function cometbackup_ChangePackage($params)
{
    return applyRestrictions($params);
}

function cometbackup_AdminSingleSignOn($params)
{
    $startSessionResult = performAPIRequest($params, [], 'account/session-start');

    if (!empty($startSessionResult['SessionKey'])) {
        $requiredParameters = base64_encode(json_encode([
            'Server' => getHost($params),
            'SessionKey' => $startSessionResult['SessionKey'],
            'TargetUser' => $params['serverusername']
        ]));
        return [
            'success' => true,
            'redirectTo' => '/admin/configservers.php?CometSSO=' . $requiredParameters
        ];
    } else {
        return [
            'success' => false,
            'errorMsg' => (empty($startSessionResult['Message']) ? 'Login failed.' : $startSessionResult['Message'])
        ];
    }
}
