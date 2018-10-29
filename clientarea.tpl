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
  		<tr>
		<td>Username</td>
		<td>{$Username}</td>
	</tr>

	<tr>
		<td>Protected Items Quota</td>
		<td>{$AllProtectedItemsQuotaBytes}</td>
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
		{php}
			$userProfile = $template->get_template_vars('userProfile');
			$userJobs = $template->get_template_vars('getJobsForUser');

			foreach ($userJobs as $job) {
				if (array_key_exists($job['SourceGUID'], $userProfile['Profile']['Sources'])) {
					echo '<tr>';
					echo '<td>'.hesc($job['DeviceName']).'</td>';
					echo '<td>'.hesc($userProfile['Profile']['Sources'][$job['SourceGUID']]['Description']).'</td>';
					echo '<td>'.hesc(formatJobType($job['Classification'])).'</td>';
					echo '<td>'.hesc(formatStatusType($job['Status'])).'</td>';
					echo '<td>'.hesc($job['TotalFiles']).'</td>';
					echo '<td>'.hesc(formatBytes($job['TotalSize'])).'</td>';
					echo '<td>'.hesc(formatBytes($job['UploadSize'])).'</td>';
					echo '<td>'.hesc(formatBytes($job['DownloadSize'])).'</td>';
					echo '<td>'.hesc(date("Y-m-d h:i", $job['StartTime'])).'</td>';
					echo '</tr>';
				}
			}
		{/php}
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
