<?php
if (is_array($applicable_connections)) {
	if (!$browse_path) {
		echo "<h2>Stored Files</h2>\n";
		
		echo '<ul>';
		foreach ($list_connections as $connection) {
			echo "<li><a href=\"connection/{$connection['id']}/\"><span class=\"icon box\"></span> <b>{$connection['name']}</b></a>";
			echo ' <span class="smalltext fadedtext nobr"> &nbsp; ' . $connection['type'] . ', ' . $connection['filecount'] . ' assets</span></li>';
		}
		echo '</ul>';
	} else {
		echo '<h2>';
		if ($browse_path == '.') {
			echo '<span class="icon box"></span> root';
		} else {
			echo '<span class="icon folder_fill"></span> ' . basename($browse_path);
		}
		echo '</h2>';
		echo '<ul>';
		foreach ($list_assets['directories'] as $location => $directory) {
			echo '<li><a href="' . ADMIN_WWW_BASE_PATH . '/assets/browse/connection/' . $connection_id . '/' . $location . '"><span class="icon folder_fill"></span> <b>' . basename($directory) . '</b></a></li>';
		}
		foreach ($list_assets['assets'] as $asset) {
			if ($asset['title'] == $asset['location']) {
				$asset_title = basename($asset['location']);
			} else {
				$asset_title = $asset['title'];
			}
			echo '<li><a href="' . ADMIN_WWW_BASE_PATH . '/assets/edit/single/' . $asset['id'] . '"><span class="icon document_alt_fill"></span> ' . $asset_title . '</a>';
			echo '<br /><span class="icon"></span> <span class="smalltext fadedtext nobr">' . $asset['location'] . '</span><br /><span class="icon"></span> <span class="smalltext fadedtext nobr">' . AdminHelper::bytesToSize($asset['size']) . ', accessed: [int]</span></li>';
		}
		echo '</ul>';
	}
}
?>