<ul class="nav nav-tabs">
  <li class="active"><a data-toggle="tab" href="#home">Home</a></li>
  <li><a data-toggle="tab" href="#jobs">Jobs List</a></li>
  <li><a data-toggle="tab" href="#downloads">Downloads</a></li>
</ul>
<div class="tab-content">
	<style>
		fieldset.downloadsPlatform, fieldset.downloadsPlatform > a {
			margin: 10px 0;
		}
		fieldset.downloadsPlatform > legend {
			margin-bottom: 0;
		}
		fieldset.downloadsPlatform > legend > i {
			margin-right: 5px;
			padding-right: 12px;
			border-right: 1px solid #CCC;
		}
		fieldset.downloadsPlatform > a {
			display: block;
		}
	</style>
    <div id="home" class="tab-pane fade in active">
        <table class="table table-striped table-bordered bg-success table-hover">
        {if $UsingCustomUsername eq false}
            <tr>
                <td>Username</td>
                <td>{$Username}</td>
            </tr>
        {/if}

            <tr>
                <td>Protected Items Quota</td>
            {if $AllProtectedItemsQuota eq 0}
                <td>Unlimited</td>
            {else}
                <td>{$AllProtectedItemsQuota}GB</td>
            {/if}
            </tr>

            <tr>
                <td>Initial Storage Vault Quota</td>
            {if $StorageVaultQuota eq false}
                <td>Unlimited</td>
            {else}
                <td>{$StorageVaultQuota}GB</td>
            {/if}
            </tr>

            <tr>
                <td>Maximum Devices</td>
                <td>{$MaximumDevices}</td>
            </tr>

            <tr>
                <td>Account Create Time</td>
                <td>{$CreateTime}</td>
            </tr>

            <tr>
                <td>Space Used</td>
                <td>{$totalSize}</td>
            </tr>
        </table>
    </div>
    <div id="jobs" class="tab-pane fade">
        <table class="table table-striped table-bordered bg-success table-hover text-left">
            <tr>
                <th>Device</th>
                <th>Protected Item</th>
                <th>Type</th>
                <th>Status</th>
                <th>Files</th>
                <th>Size</th>
                <th>Uploaded</th>
                <th>Downloaded</th>
                <th>Started</th>
            </tr>
        {foreach from=$getJobsForUser item=job}
            <tr>
                <td>{$job['DeviceName']}</td>
                <td>{$job['SourceDescription']}</td>
                <td>{$job['Classification']}</td>
                <td>{$job['Status']}</td>
                <td>{$job['TotalFiles']}</td>
                <td>{$job['TotalSize']}</td>
                <td>{$job['UploadSize']}</td>
                <td>{$job['DownloadSize']}</td>
                <td>{$job['StartTime']}</td>
            </tr>
        {/foreach}
        </table>
    </div>
    <div id="downloads" class="tab-pane fade">
        <div>
            <br>
            <fieldset class="downloadsPlatform">
                <legend><i class="fab fa-windows"></i> Download Windows Client:</legend>
                <a href="?action={$smarty.get.action}&id={$smarty.get.id}&type=downloadResponseWindowsAnyCPUZip">Windows (Any CPU) Installer</a>
                <a href="?action={$smarty.get.action}&id={$smarty.get.id}&type=downloadResponseWindowsX86_32Zip">Windows (32-bit) Installer</a>
                <a href="?action={$smarty.get.action}&id={$smarty.get.id}&type=downloadResponseWindowsX86_64Zip">Windows (64-bit) Installer</a>
            </fieldset>

            <fieldset class="downloadsPlatform">
                <legend><i class="fab fa-linux"></i> Download Linux Client:</legend>
                <a href="?action={$smarty.get.action}&id={$smarty.get.id}&type=downloadResponseLinux">Linux (generic) Installer</a>
            </fieldset>

            <fieldset class="downloadsPlatform">
                <legend><i class="fab fa-apple"></i> Download Mac Client:</legend>
                <a href="?action={$smarty.get.action}&id={$smarty.get.id}&type=downloadResponseMacOSX86">macOS Installer</a>
            </fieldset>
        </div>
    </div>
</div>
