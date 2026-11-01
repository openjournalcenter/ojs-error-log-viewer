{extends file="layouts/backend.tpl"}

{block name="breadcrumbs"}{/block}

{block name="page"}
	<div class="pkp_page_header" style="margin-bottom: 1.5rem;">
		<h1 class="app__pageHeading" style="margin: 0 0 0.5rem 0;">{translate key="plugins.generic.errorLogViewer.title"}</h1>
		<p style="color: #64748b; font-size: 0.875rem; margin: 0;">{translate key="plugins.generic.errorLogViewer.subtitle"}</p>
	</div>

	<div style="display: flex; flex-direction: column; gap: 0.75rem; margin-bottom: 1rem;">
		<div style="display: flex; gap: 0.75rem; align-items: center; width: 100%;">
			<input type="text" id="logSearchInput" class="pkpFormField__input" placeholder="{translate key="plugins.generic.errorLogViewer.filter"}" style="flex: 1; width: 100%; border-radius: 0;" />
			
			<select id="logLevelFilter" class="pkpFormField__input" style="background: #fff; border-radius: 0; min-width: 140px; flex-shrink: 0;">
				<option value="all">{translate key="plugins.generic.errorLogViewer.filterAll"}</option>
				<option value="fatal">{translate key="plugins.generic.errorLogViewer.filterFatal"}</option>
				<option value="warning">{translate key="plugins.generic.errorLogViewer.filterWarning"}</option>
				<option value="notice">{translate key="plugins.generic.errorLogViewer.filterNotice"}</option>
			</select>

			<select id="linesCountSelect" class="pkpFormField__input" style="background: #fff; border-radius: 0; min-width: 120px; flex-shrink: 0;">
				<option value="100">100 Lines</option>
				<option value="250" selected="selected">250 Lines</option>
				<option value="500">500 Lines</option>
				<option value="1000">1000 Lines</option>
			</select>
		</div>

		<div style="display: flex; justify-content: flex-end; gap: 0.5rem; align-items: center; flex-wrap: wrap;">
			<button type="button" id="copyDiagnosticsBtn" class="pkpButton">
				{translate key="plugins.generic.errorLogViewer.copyDiagnostics"}
			</button>
			<a href="{url page="errorLogViewer" op="downloadLog"}" class="pkpButton" target="_blank">
				{translate key="plugins.generic.errorLogViewer.download"}
			</a>
			<button type="button" id="clearLogBtn" class="pkpButton pkpButton--isWarnable">
				{translate key="plugins.generic.errorLogViewer.clear"}
			</button>
		</div>
	</div>

	<div style="border: 1px solid #1e293b; background: #0f172a; overflow: hidden; margin-bottom: 2rem;">
		<div style="padding: 0.6rem 1rem; background: #1e293b; color: #94a3b8; font-size: 0.75rem; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #334155; flex-wrap: wrap; gap: 0.5rem;">
			<div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
				<span id="logPathDisplay" style="font-weight: 600; color: #38bdf8; word-break: break-all;">{$logPath|escape}</span>
				<span style="color: #475569;">|</span>
				<span>{translate key="plugins.generic.errorLogViewer.logSize"}: <strong id="fileSizeDisplay" style="color: #f1f5f9;">{$fileSize}</strong></span>
				<span style="color: #475569;">|</span>
				<span>{translate key="plugins.generic.errorLogViewer.lastModified"}: <span id="lastModifiedDisplay" style="color: #f1f5f9;">{$lastModified}</span></span>
			</div>
			<span id="logCounter" style="color: #94a3b8;">0 entries loaded</span>
		</div>
		<div id="logConsoleContainer" style="padding: 1rem; height: 500px; overflow-y: auto; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace; font-size: 0.8125rem; line-height: 1.6; color: #e2e8f0;">
			<div style="color: #94a3b8; text-align: center; padding: 2rem;">Loading log entries...</div>
		</div>
	</div>

	<div style="background: #ffffff; border: 1px solid #e2e8f0; padding: 1.25rem;">
		<h4 class="pkpFormFieldLabel" style="margin: 0 0 0.5rem 0; font-size: 0.95rem; color: #1e293b;">{translate key="plugins.generic.errorLogViewer.customPath"}</h4>
		<p style="color: #64748b; font-size: 0.8125rem; margin-bottom: 0.75rem;">{translate key="plugins.generic.errorLogViewer.customPathDescription"}</p>
		<form id="customPathForm" style="display: flex; gap: 0.75rem; width: 100%;">
			{csrf}
			<input type="text" id="customLogPath" name="customLogPath" value="{$customPath|escape}" placeholder="/var/log/php-error.log" class="pkpFormField__input" style="flex: 1; width: 100%; border-radius: 0;" />
			<button type="submit" class="pkpButton" style="flex-shrink: 0;">{translate key="plugins.generic.errorLogViewer.saveSettings"}</button>
		</form>
	</div>

	<script type="text/javascript">
		$(function() {ldelim}
			var logsData = [];
			var currentSummary = "";

			var $container = $('#logConsoleContainer');
			var $counter = $('#logCounter');
			var $searchInput = $('#logSearchInput');
			var $levelFilter = $('#logLevelFilter');
			var $linesSelect = $('#linesCountSelect');
			var $copyBtn = $('#copyDiagnosticsBtn');
			var $clearBtn = $('#clearLogBtn');
			var $customForm = $('#customPathForm');

			function renderLogs() {ldelim}
				var query = ($searchInput.val() || "").toLowerCase().trim();
				var selectedLevel = $levelFilter.val();

				var filtered = logsData.filter(function(item) {ldelim}
					if (selectedLevel !== "all" && item.level !== selectedLevel) {ldelim}
						return false;
					{rdelim}
					if (query.length > 0 && item.raw.toLowerCase().indexOf(query) === -1) {ldelim}
						return false;
					{rdelim}
					return true;
				{rdelim});

				$counter.text(filtered.length + " / " + logsData.length + " entries displayed");

				if (filtered.length === 0) {ldelim}
					$container.html('<div style="color: #94a3b8; text-align: center; padding: 3rem 1rem;">{translate key="plugins.generic.errorLogViewer.empty"|escape:"javascript"}</div>');
					return;
				{rdelim}

				var html = "";
				$.each(filtered, function(i, item) {ldelim}
					var color = "#e2e8f0";
					var bg = "transparent";
					if (item.level === "fatal") {ldelim}
						color = "#fca5a5";
						bg = "rgba(239, 68, 68, 0.15)";
					{rdelim} else if (item.level === "warning") {ldelim}
						color = "#fcd34d";
						bg = "rgba(245, 158, 11, 0.1)";
					{rdelim} else if (item.level === "notice") {ldelim}
						color = "#93c5fd";
					{rdelim}

					var escaped = $('<div>').text(item.raw).html();
					html += '<div style="padding: 0.25rem 0.5rem; margin-bottom: 2px; background: ' + bg + '; color: ' + color + '; white-space: pre-wrap; word-break: break-all;">' + escaped + '</div>';
				{rdelim});

				$container.html(html);
				$container.scrollTop($container[0].scrollHeight);
			{rdelim}

			function fetchLogs() {ldelim}
				$container.html('<div style="color: #94a3b8; text-align: center; padding: 3rem 1rem;">Loading log entries...</div>');
				var lines = $linesSelect.val();
				$.ajax({ldelim}
					url: '{url page="errorLogViewer" op="getLogs"}',
					type: 'GET',
					data: {ldelim} lines: lines {rdelim},
					dataType: 'json',
					success: function(response) {ldelim}
						if (response && response.status === true) {ldelim}
							logsData = Array.isArray(response.content) ? response.content : [];
							currentSummary = (response.additionalAttributes && response.additionalAttributes.diagnosticsSummary) || "";
							if (response.additionalAttributes) {ldelim}
								if (response.additionalAttributes.fileSize) {ldelim}
									$('#fileSizeDisplay').text(response.additionalAttributes.fileSize);
								{rdelim}
								if (response.additionalAttributes.lastModified) {ldelim}
									$('#lastModifiedDisplay').text(response.additionalAttributes.lastModified);
								{rdelim}
							{rdelim}
							renderLogs();
						{rdelim} else {ldelim}
							var msg = (response && response.content) ? response.content : "Error loading logs.";
							$container.html('<div style="color: #f87171; text-align: center; padding: 3rem 1rem;">' + $('<div>').text(msg).html() + '</div>');
						{rdelim}
					{rdelim},
					error: function(xhr, status, err) {ldelim}
						$container.html('<div style="color: #f87171; text-align: center; padding: 3rem 1rem;">Failed to connect to error log endpoint (' + status + ').</div>');
					{rdelim}
				{rdelim});
			{rdelim}

			$searchInput.on('input', renderLogs);
			$levelFilter.on('change', renderLogs);
			$linesSelect.on('change', fetchLogs);

			$copyBtn.on('click', function() {ldelim}
				if (!currentSummary) {ldelim}
					currentSummary = logsData.map(function(item) {ldelim} return item.raw; {rdelim}).slice(-20).join("\n");
				{rdelim}
				if (!currentSummary) return;

				if (navigator.clipboard && navigator.clipboard.writeText) {ldelim}
					navigator.clipboard.writeText(currentSummary).then(function() {ldelim}
						alert('{translate key="plugins.generic.errorLogViewer.copied"|escape:"javascript"}');
					{rdelim});
				{rdelim} else {ldelim}
					var ta = document.createElement("textarea");
					ta.value = currentSummary;
					document.body.appendChild(ta);
					ta.select();
					document.execCommand("copy");
					document.body.removeChild(ta);
					alert('{translate key="plugins.generic.errorLogViewer.copied"|escape:"javascript"}');
				{rdelim}
			{rdelim});

			$clearBtn.on('click', function() {ldelim}
				if (!confirm('{translate key="plugins.generic.errorLogViewer.clearConfirm"|escape:"javascript"}')) {ldelim}
					return;
				{rdelim}
				$.ajax({ldelim}
					url: '{url page="errorLogViewer" op="clearLog"}',
					type: 'POST',
					data: {ldelim} csrfToken: pkp.currentUser ? pkp.currentUser.csrfToken : '' {rdelim},
					dataType: 'json',
					success: function(res) {ldelim}
						if (res && res.status) {ldelim}
							fetchLogs();
						{rdelim} else {ldelim}
							alert((res && res.content) || "Failed to clear log.");
						{rdelim}
					{rdelim}
				{rdelim});
			{rdelim});

			$customForm.on('submit', function(e) {ldelim}
				e.preventDefault();
				var path = $('#customLogPath').val();
				$.ajax({ldelim}
					url: '{url page="errorLogViewer" op="saveSettings"}',
					type: 'POST',
					data: {ldelim}
						customLogPath: path,
						csrfToken: pkp.currentUser ? pkp.currentUser.csrfToken : ''
					{rdelim},
					dataType: 'json',
					success: function(res) {ldelim}
						if (res && res.status) {ldelim}
							window.location.reload();
						{rdelim} else {ldelim}
							alert((res && res.content) || "Failed to save log path.");
						{rdelim}
					{rdelim}
				{rdelim});
			{rdelim});

			fetchLogs();
		{rdelim});
	</script>
{/block}

