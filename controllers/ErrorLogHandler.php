<?php

namespace APP\plugins\generic\errorLogViewer\controllers;

use APP\handler\Handler;
use APP\notification\NotificationManager;
use APP\plugins\generic\errorLogViewer\classes\ErrorLogHelper;
use APP\template\TemplateManager;
use PKP\config\Config;
use PKP\core\JSONMessage;
use PKP\core\PKPApplication;
use PKP\notification\PKPNotification;
use PKP\security\Role;
use PKP\security\Validation;

class ErrorLogHandler extends Handler
{
	public $_isBackendPage = true;
	public object $plugin;

	public function __construct(object $plugin)
	{
		parent::__construct();
		$this->plugin = $plugin;
		$this->addRoleAssignment(
			[Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_MANAGER],
			['index', 'getLogs', 'clearLog', 'downloadLog', 'saveSettings']
		);
	}

	public function authorize($request, &$args, $roleAssignments)
	{
		if (!Validation::isLoggedIn()) {
			Validation::redirectLogin();
			return false;
		}

		$isSiteAdmin = Validation::isSiteAdmin();
		$context = $request->getContext();
		$isManager = $context && Validation::isAuthorized(Role::ROLE_ID_MANAGER, $context->getId());

		if (!$isSiteAdmin && !$isManager) {
			return false;
		}

		$this->markRoleAssignmentsChecked();
		return parent::authorize($request, $args, $roleAssignments);
	}

	public function index($args, $request)
	{
		$templateMgr = TemplateManager::getManager($request);
		$this->setupTemplate($request);

		$context = $request->getContext();
		$contextId = $context ? $context->getId() : PKPApplication::CONTEXT_SITE;

		$customPath = (string) $this->plugin->getSetting($contextId, 'customLogPath');
		$resolvedPath = ErrorLogHelper::resolveLogPath($customPath);
		$fileExists = !empty($resolvedPath) && file_exists($resolvedPath);

		$fileSize = $fileExists ? ErrorLogHelper::formatBytes(filesize($resolvedPath)) : '0 B';
		$lastModified = $fileExists ? date('Y-m-d H:i:s', filemtime($resolvedPath)) : '-';

		$templateMgr->assign([
			'pageTitle' => $this->plugin->getDisplayName(),
			'logPath' => $resolvedPath,
			'customPath' => $customPath,
			'fileExists' => $fileExists,
			'fileSize' => $fileSize,
			'lastModified' => $lastModified,
		]);

		return $templateMgr->display($this->plugin->getTemplateResource('index.tpl'));
	}

	public function getLogs($args, $request): JSONMessage
	{
		$context = $request->getContext();
		$contextId = $context ? $context->getId() : PKPApplication::CONTEXT_SITE;

		$customPath = (string) $this->plugin->getSetting($contextId, 'customLogPath');
		$resolvedPath = ErrorLogHelper::resolveLogPath($customPath);

		if (!file_exists($resolvedPath)) {
			return new JSONMessage(false, __('plugins.generic.errorLogViewer.notFound'));
		}

		$linesCount = (int) ($request->getUserVar('lines') ?: 250);
		if ($linesCount <= 0 || $linesCount > 2000) {
			$linesCount = 250;
		}

		$parsedLines = ErrorLogHelper::readTail($resolvedPath, $linesCount);
		$summary = ErrorLogHelper::getDiagnosticsSummary($parsedLines);

		$json = new JSONMessage(true, $parsedLines);
		$json->setAdditionalAttributes([
			'fileSize' => ErrorLogHelper::formatBytes(filesize($resolvedPath)),
			'lastModified' => date('Y-m-d H:i:s', filemtime($resolvedPath)),
			'diagnosticsSummary' => $summary,
		]);

		return $json;
	}

	public function clearLog($args, $request): JSONMessage
	{
		$context = $request->getContext();
		$contextId = $context ? $context->getId() : PKPApplication::CONTEXT_SITE;

		$customPath = (string) $this->plugin->getSetting($contextId, 'customLogPath');
		$resolvedPath = ErrorLogHelper::resolveLogPath($customPath);

		if (!file_exists($resolvedPath) || !is_writable($resolvedPath)) {
			return new JSONMessage(false, __('plugins.generic.errorLogViewer.notFound'));
		}

		file_put_contents($resolvedPath, '');

		$notificationMgr = new NotificationManager();
		$notificationMgr->createTrivialNotification(
			$request->getUser()->getId(),
			PKPNotification::NOTIFICATION_TYPE_SUCCESS,
			['contents' => __('plugins.generic.errorLogViewer.cleared')]
		);

		return new JSONMessage(true);
	}

	public function downloadLog($args, $request): void
	{
		$context = $request->getContext();
		$contextId = $context ? $context->getId() : PKPApplication::CONTEXT_SITE;

		$customPath = (string) $this->plugin->getSetting($contextId, 'customLogPath');
		$resolvedPath = ErrorLogHelper::resolveLogPath($customPath);

		if (!file_exists($resolvedPath) || !is_readable($resolvedPath)) {
			$request->redirect(null, 'errorLogViewer');
		}

		header('Content-Type: text/plain; charset=UTF-8');
		header('Content-Disposition: attachment; filename="ojs_error_log_' . date('Ymd_His') . '.txt"');
		header('Content-Length: ' . filesize($resolvedPath));
		header('X-Content-Type-Options: nosniff');
		readfile($resolvedPath);
		exit;
	}

	public function saveSettings($args, $request): JSONMessage
	{
		$context = $request->getContext();
		$contextId = $context ? $context->getId() : PKPApplication::CONTEXT_SITE;

		$customLogPath = trim((string) $request->getUserVar('customLogPath'));
		if (!empty($customLogPath)) {
			$realPath = realpath($customLogPath);
			$extension = strtolower(pathinfo($customLogPath, PATHINFO_EXTENSION));
			$basename = strtolower(basename($customLogPath));
			$isLogFilename = in_array($extension, ['log', 'txt']) || str_contains($basename, 'log');

			if (!$realPath || !file_exists($realPath) || !is_readable($realPath) || is_dir($realPath) || !$isLogFilename) {
				return new JSONMessage(false, __('plugins.generic.errorLogViewer.notFound'));
			}

			if ($realPath === realpath(Config::getConfigFileName())) {
				return new JSONMessage(false, __('plugins.generic.errorLogViewer.unauthorized'));
			}
		}

		$this->plugin->updateSetting($contextId, 'customLogPath', $customLogPath);

		$notificationMgr = new NotificationManager();
		$notificationMgr->createTrivialNotification(
			$request->getUser()->getId(),
			PKPNotification::NOTIFICATION_TYPE_SUCCESS,
			['contents' => __('plugins.generic.errorLogViewer.settingsSaved')]
		);

		return new JSONMessage(true);
	}
}
