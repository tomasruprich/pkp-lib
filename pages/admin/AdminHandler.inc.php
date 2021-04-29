<?php

/**
 * @file pages/admin/AdminHandler.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class AdminHandler
 * @ingroup pages_admin
 *
 * @brief Handle requests for site administration functions.
 */

use APP\core\Services;
use APP\file\PublicFileManager;
use APP\handler\Handler;
use APP\template\TemplateManager;

use Illuminate\Support\Facades\DB;

class AdminHandler extends Handler
{
    /** @copydoc PKPHandler::_isBackendPage */
    public $_isBackendPage = true;

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();

        $this->addRoleAssignment(
            [ROLE_ID_SITE_ADMIN],
            [
                'index',
                'contexts',
                'settings',
                'wizard',
                'systemInfo',
                'phpinfo',
                'expireSessions',
                'clearTemplateCache',
                'clearDataCache',
  				'rebuildSearchIndex',
                'downloadScheduledTaskLogFile',
                'clearScheduledTaskLogFiles',
            ]
        );
    }

    /**
     * @copydoc PKPHandler::authorize()
     */
    public function authorize($request, &$args, $roleAssignments)
    {
        import('lib.pkp.classes.security.authorization.PKPSiteAccessPolicy');
        $this->addPolicy(new PKPSiteAccessPolicy($request, null, $roleAssignments));
        $returner = parent::authorize($request, $args, $roleAssignments);

        // Admin shouldn't access this page from a specific context
        if ($request->getContext()) {
            return false;
        }

        return $returner;
    }

    /**
     * @copydoc PKPHandler::initialize()
     */
    public function initialize($request)
    {
        AppLocale::requireComponents(
            LOCALE_COMPONENT_PKP_ADMIN,
            LOCALE_COMPONENT_APP_MANAGER,
            LOCALE_COMPONENT_APP_ADMIN,
            LOCALE_COMPONENT_APP_COMMON,
            LOCALE_COMPONENT_PKP_USER,
            LOCALE_COMPONENT_PKP_MANAGER
        );
        $templateMgr = TemplateManager::getManager($request);

        $templateMgr->assign([
            'pageComponent' => 'AdminPage',
        ]);

        if ($request->getRequestedOp() !== 'index') {
            $router = $request->getRouter();
            $templateMgr->assign([
                'breadcrumbs' => [
                    [
                        'id' => 'admin',
                        'url' => $router->url($request, 'index', 'admin'),
                        'name' => __('navigation.admin'),
                    ]
                ]
            ]);
        }

        // Interact with the beacon (if enabled) and determine if a new version exists
        import('lib.pkp.classes.site.VersionCheck');
        $latestVersion = VersionCheck::checkIfNewVersionExists();

        // Display a warning message if there is a new version of OJS available
        if (Config::getVar('general', 'show_upgrade_warning') && $latestVersion) {
            $currentVersion = VersionCheck::getCurrentDBVersion();
            $templateMgr->assign([
                'newVersionAvailable' => true,
                'currentVersion' => $currentVersion,
                'latestVersion' => $latestVersion,
            ]);
        }

        return parent::initialize($request);
    }

    /**
     * Display site admin index page.
     *
     * @param $args array
     * @param $request PKPRequest
     */
    public function index($args, $request)
    {
        $this->setupTemplate($request);
        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign([
            'pageTitle' => __('admin.siteAdmin'),
        ]);
        $templateMgr->display('admin/index.tpl');
    }

    /**
     * Display a list of the contexts hosted on the site.
     *
     * @param $args array
     * @param $request PKPRequest
     */
    public function contexts($args, $request)
    {
        $this->setupTemplate($request);
        $templateMgr = TemplateManager::getManager($request);
        $breadcrumbs = $templateMgr->get_template_vars('breadcrumbs');
        $breadcrumbs[] = [
            'id' => 'contexts',
            'name' => __('admin.hostedContexts'),
        ];
        $templateMgr->assign([
            'breadcrumbs' => $breadcrumbs,
            'pageTitle' => __('admin.hostedContexts'),
        ]);
        $templateMgr->display('admin/contexts.tpl');
    }

    /**
     * Display the administration settings page.
     *
     * @param $args array
     * @param $request PKPRequest
     */
    public function settings($args, $request)
    {
        $this->setupTemplate($request);
        $site = $request->getSite();
        $dispatcher = $request->getDispatcher();

        $apiUrl = $dispatcher->url($request, PKPApplication::ROUTE_API, CONTEXT_ID_ALL, 'site');
        $themeApiUrl = $dispatcher->url($request, PKPApplication::ROUTE_API, CONTEXT_ID_ALL, 'site/theme');
        $temporaryFileApiUrl = $dispatcher->url($request, PKPApplication::ROUTE_API, CONTEXT_ID_ALL, 'temporaryFiles');

        $publicFileManager = new PublicFileManager();
        $baseUrl = $request->getBaseUrl() . '/' . $publicFileManager->getSiteFilesPath();

        $supportedLocales = $site->getSupportedLocales();
        $localeNames = AppLocale::getAllLocales();
        $locales = array_map(function ($localeKey) use ($localeNames) {
            return ['key' => $localeKey, 'label' => $localeNames[$localeKey]];
        }, $supportedLocales);

        $contexts = Services::get('context')->getManySummary();

        $siteAppearanceForm = new \PKP\components\forms\site\PKPSiteAppearanceForm($apiUrl, $locales, $site, $baseUrl, $temporaryFileApiUrl);
        $siteConfigForm = new \PKP\components\forms\site\PKPSiteConfigForm($apiUrl, $locales, $site);
        $siteInformationForm = new \PKP\components\forms\site\PKPSiteInformationForm($apiUrl, $locales, $site);
        $siteBulkEmailsForm = new \PKP\components\forms\site\PKPSiteBulkEmailsForm($apiUrl, $site, $contexts);
        $themeForm = new \PKP\components\forms\context\PKPThemeForm($themeApiUrl, $locales);

        $templateMgr = TemplateManager::getManager($request);

        $templateMgr->setState([
            'components' => [
                FORM_SITE_APPEARANCE => $siteAppearanceForm->getConfig(),
                FORM_SITE_CONFIG => $siteConfigForm->getConfig(),
                FORM_SITE_INFO => $siteInformationForm->getConfig(),
                FORM_SITE_BULK_EMAILS => $siteBulkEmailsForm->getConfig(),
                FORM_THEME => $themeForm->getConfig(),
            ],
        ]);

        $breadcrumbs = $templateMgr->get_template_vars('breadcrumbs');
        $breadcrumbs[] = [
            'id' => 'settings',
            'name' => __('admin.siteSettings'),
        ];
        $templateMgr->assign([
            'breadcrumbs' => $breadcrumbs,
            'pageTitle' => __('admin.siteSettings'),
            'componentAvailability' => $this->siteSettingsAvailability($request),
        ]);

        $templateMgr->display('admin/settings.tpl');
    }

    /**
     * Business logic for site settings single/multiple contexts availability
     *
     * @param $request PKPRequest
     *
     * @return array [siteComponent, availability (bool)]
     */
    private function siteSettingsAvailability($request)
    {
        $tabsSingleContextAvailability = [
            'siteSetup',
            'languages',
            'bulkEmails',
        ];

        $tabs = [
            'siteSetup',
            'siteAppearance',
            'sitePlugins',
            'siteConfig',
            'siteInfo',
            'languages',
            'navigationMenus',
            'bulkEmails',
            'siteTheme',
            'siteAppearanceSetup',
        ];

        $singleContextSite = (Services::get('context')->getCount() == 1);

        $tabsAvailability = [];

        foreach ($tabs as $tab) {
            $tabsAvailability[$tab] = true;
            if ($singleContextSite && !in_array($tab, $tabsSingleContextAvailability)) {
                $tabsAvailability[$tab] = false;
            }
        }

        return $tabsAvailability;
    }

    /**
     * Display a settings wizard for a journal
     *
     * @param $args array
     * @param $request PKPRequest
     */
    public function wizard($args, $request)
    {
        $this->setupTemplate($request);
        $router = $request->getRouter();
        $dispatcher = $request->getDispatcher();

        if (!isset($args[0]) || !ctype_digit((string) $args[0])) {
            $request->getDispatcher()->handle404();
        }

        $contextService = Services::get('context');
        $context = $contextService->get((int) $args[0]);

        if (empty($context)) {
            $request->getDispatcher()->handle404();
        }

        $apiUrl = $dispatcher->url($request, PKPApplication::ROUTE_API, $context->getPath(), 'contexts/' . $context->getId());
        $themeApiUrl = $dispatcher->url($request, PKPApplication::ROUTE_API, $context->getPath(), 'contexts/' . $context->getId() . '/theme');
        $sitemapUrl = $router->url($request, $context->getPath(), 'sitemap');

        $supportedFormLocales = $context->getSupportedFormLocales();
        $localeNames = AppLocale::getAllLocales();
        $locales = array_map(function ($localeKey) use ($localeNames) {
            return ['key' => $localeKey, 'label' => $localeNames[$localeKey]];
        }, $supportedFormLocales);

        $contextForm = new APP\components\forms\context\ContextForm($apiUrl, $locales, $request->getBaseUrl(), $context);
        $themeForm = new PKP\components\forms\context\PKPThemeForm($themeApiUrl, $locales, $context);
        $indexingForm = new PKP\components\forms\context\PKPSearchIndexingForm($apiUrl, $locales, $context, $sitemapUrl);

        $components = [
            FORM_CONTEXT => $contextForm->getConfig(),
            FORM_SEARCH_INDEXING => $indexingForm->getConfig(),
            FORM_THEME => $themeForm->getConfig(),
        ];

        $bulkEmailsEnabled = in_array($context->getId(), (array) $request->getSite()->getData('enableBulkEmails'));
        if ($bulkEmailsEnabled) {
            $userGroups = DAORegistry::getDAO('UserGroupDAO')->getByContextId($context->getId());
            $restrictBulkEmailsForm = new PKP\components\forms\context\PKPRestrictBulkEmailsForm($apiUrl, $context, $userGroups);
            $components[$restrictBulkEmailsForm->id] = $restrictBulkEmailsForm->getConfig();
        }

        $templateMgr = TemplateManager::getManager($request);

        $templateMgr->setState([
            'components' => $components,
        ]);

        $breadcrumbs = $templateMgr->get_template_vars('breadcrumbs');
        $breadcrumbs[] = [
            'id' => 'contexts',
            'name' => __('admin.hostedContexts'),
            'url' => $router->url($request, 'index', 'admin', 'contexts'),
        ];
        $breadcrumbs[] = [
            'id' => 'wizard',
            'name' => __('manager.settings.wizard'),
        ];

        $templateMgr->assign([
            'breadcrumbs' => $breadcrumbs,
            'bulkEmailsEnabled' => $bulkEmailsEnabled,
            'editContext' => $context,
            'pageTitle' => __('manager.settings.wizard'),
        ]);

        $templateMgr->display('admin/contextSettings.tpl');
    }

    /**
     * Show system information summary.
     *
     * @param $args array
     * @param $request PKPRequest
     */
    public function systemInfo($args, $request)
    {
        $this->setupTemplate($request, true);

        $versionDao = DAORegistry::getDAO('VersionDAO'); /** @var VersionDAO $versionDao */
        $currentVersion = $versionDao->getCurrentVersion();

        if ($request->getUserVar('versionCheck')) {
            $latestVersionInfo = VersionCheck::getLatestVersion();
            $latestVersionInfo['patch'] = VersionCheck::getPatch($latestVersionInfo);
        }

        $versionDao = DAORegistry::getDAO('VersionDAO'); /** @var VersionDAO $versionDao */
        $versionHistory = $versionDao->getVersionHistory();
        $pdo = DB::getPDO();

        $serverInfo = [
            'admin.server.platform' => PHP_OS,
            'admin.server.phpVersion' => phpversion(),
            'admin.server.apacheVersion' => $_SERVER['SERVER_SOFTWARE'],
            'admin.server.dbDriver' => $pdo->getAttribute(PDO::ATTR_DRIVER_NAME),
            'admin.server.dbVersion' => $pdo->getAttribute(PDO::ATTR_SERVER_VERSION),
        ];

        $templateMgr = TemplateManager::getManager($request);

        $breadcrumbs = $templateMgr->get_template_vars('breadcrumbs');
        $breadcrumbs[] = [
            'id' => 'wizard',
            'name' => __('admin.systemInformation'),
        ];

        $templateMgr->assign([
            'breadcrumbs' => $breadcrumbs,
            'currentVersion' => $currentVersion,
            'latestVersionInfo' => $latestVersionInfo,
            'pageTitle' => __('admin.systemInformation'),
            'versionHistory' => $versionHistory,
            'serverInfo' => $serverInfo,
            'configData' => Config::getData(),
        ]);

        $templateMgr->display('admin/systemInfo.tpl');
    }

    /**
     * Show full PHP configuration information.
     */
    public function phpinfo()
    {
        phpinfo();
    }

    /**
     * Expire all user sessions (will log out all users currently logged in).
     *
     * @param $args array
     * @param $request PKPRequest
     */
    public function expireSessions($args, $request)
    {
        $sessionDao = DAORegistry::getDAO('SessionDAO'); /** @var SessionDAO $sessionDao */
        $sessionDao->deleteAllSessions();
        $request->redirect(null, 'admin');
    }

    /**
     * Clear compiled templates.
     *
     * @param $args array
     * @param $request PKPRequest
     */
    public function clearTemplateCache($args, $request)
    {
        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->clearTemplateCache();
        $templateMgr->clearCssCache();
        $request->redirect(null, 'admin');
    }

    /**
     * Clear the data cache.
     *
     * @param $args array
     * @param $request PKPRequest
     */
    public function clearDataCache($args, $request)
    {
        // Clear the CacheManager's caches
        $cacheManager = CacheManager::getManager();
        $cacheManager->flush();

        // Clear ADODB's cache
        $userDao = DAORegistry::getDAO('UserDAO'); // As good as any
        $userDao->flushCache();

        $request->redirect(null, 'admin');
    }

    # Testing of search index rebuild from web
	public function callbackBaseUrl($hookName, $params) {
		$baseUrl =& $params[0];
		$baseUrl = Config::getVar('general', 'base_url');
		return true;
	}
	function rebuildSearchIndex() {
		$switches = array();
		$journal = null;
		HookRegistry::register('Request::getBaseUrl', array($this, 'callbackBaseUrl'));
		$articleSearchIndex = Application::getSubmissionSearchIndex();
		$articleSearchIndex->rebuildIndex(true, $journal, $switches);
	}
    
    /**
     * Download scheduled task execution log file.
     */
    public function downloadScheduledTaskLogFile()
    {
        $request = Application::get()->getRequest();

        $file = basename($request->getUserVar('file'));
        import('lib.pkp.classes.scheduledTask.ScheduledTaskHelper');
        ScheduledTaskHelper::downloadExecutionLog($file);
    }

    /**
     * Clear scheduled tasks execution logs.
     */
    public function clearScheduledTaskLogFiles()
    {
        import('lib.pkp.classes.scheduledTask.ScheduledTaskHelper');
        ScheduledTaskHelper::clearExecutionLogs();

        $request = Application::get()->getRequest();
        $request->redirect(null, 'admin');
    }
}
