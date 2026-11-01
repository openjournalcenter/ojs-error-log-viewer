<?php

namespace APP\plugins\generic\errorLogViewer;

use APP\core\Application;
use APP\plugins\generic\errorLogViewer\controllers\ErrorLogHandler;
use PKP\plugins\GenericPlugin;
use PKP\plugins\Hook;
use PKP\security\Role;
use PKP\security\Validation;

class ErrorLogViewerPlugin extends GenericPlugin
{
	public function register($category, $path, $mainContextId = null): bool
	{
		$success = parent::register($category, $path, $mainContextId);
		if (Application::isUnderMaintenance()) {
			return true;
		}

		if ($success && $this->getEnabled($mainContextId)) {
			Hook::add('LoadHandler', [$this, 'setPageHandler']);
			Hook::add('TemplateManager::display', [$this, 'handleTemplateDisplay']);
		}

		return $success;
	}

	public function isSitePlugin(): bool
	{
		return false;
	}

	public function getDisplayName(): string
	{
		return __('plugins.generic.errorLogViewer.displayName');
	}

	public function getDescription(): string
	{
		return __('plugins.generic.errorLogViewer.description');
	}

	public function setPageHandler(string $hookName, array $params): bool
	{
		$page = &$params[0];
		$handler = &$params[3];

		if ($this->getEnabled() && $page === 'errorLogViewer') {
			$handler = new ErrorLogHandler($this);
			return true;
		}

		return false;
	}

	public function handleTemplateDisplay(string $hookName, array $params): bool
	{
		$templateMgr = $params[0];
		$request = Application::get()->getRequest();
		$context = $request->getContext();

		if (!$context || !Validation::isLoggedIn()) {
			return false;
		}

		$isAdmin = Validation::isSiteAdmin();
		$isManager = Validation::isAuthorized(Role::ROLE_ID_MANAGER, $context->getId());

		if ($isAdmin || $isManager) {
			$menu = (array) $templateMgr->getState('menu');
			$url = $request->getDispatcher()->url($request, Application::ROUTE_PAGE, null, 'errorLogViewer');

			$errorLogMenuItem = [
				'name' => __('plugins.generic.errorLogViewer.menu'),
				'url' => $url,
				'isCurrent' => $request->getRequestedPage() === 'errorLogViewer',
			];

			$pos = false;
			$keys = array_keys($menu);
			foreach (['admin', 'tools', 'settings'] as $targetKey) {
				$foundPos = array_search($targetKey, $keys, true);
				if ($foundPos !== false && ($pos === false || $foundPos > $pos)) {
					$pos = $foundPos;
				}
			}

			if ($pos !== false) {
				$menu = array_slice($menu, 0, $pos + 1, true)
					+ ['errorLogViewer' => $errorLogMenuItem]
					+ array_slice($menu, $pos + 1, null, true);
			} else {
				$menu['errorLogViewer'] = $errorLogMenuItem;
			}

			$templateMgr->setState(['menu' => $menu]);
		}

		return false;
	}
}
