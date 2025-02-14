<?php

/**
 * @file controllers/tab/pubIds/form/PKPPublicIdentifiersForm.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPPublicIdentifiersForm
 * @ingroup controllers_tab_pubIds_form
 *
 * @brief Displays a pub ids form.
 */

use APP\template\TemplateManager;
use PKP\form\Form;

use PKP\plugins\PKPPubIdPluginHelper;

class PKPPublicIdentifiersForm extends Form
{
    /** @var int The context id */
    public $_contextId;

    /** @var object The pub object the identifiers are edited of
     * 	Submission, Representation, SubmissionFile, OJS Issue and OMP Chapter
     */
    public $_pubObject;

    /** @var int The current stage id, WORKFLOW_STAGE_ID_ */
    public $_stageId;

    /**
     * @var array Parameters to configure the form template.
     */
    public $_formParams;

    /**
     * Constructor.
     *
     * @param $pubObject object
     * @param $stageId integer
     * @param $formParams array
     */
    public function __construct($pubObject, $stageId = null, $formParams = null)
    {
        parent::__construct('controllers/tab/pubIds/form/publicIdentifiersForm.tpl');

        $this->_pubObject = $pubObject;
        $this->_stageId = $stageId;
        $this->_formParams = $formParams;

        $request = Application::get()->getRequest();
        $context = $request->getContext();
        $this->_contextId = $context->getId();

        AppLocale::requireComponents(LOCALE_COMPONENT_PKP_EDITOR);

        $this->addCheck(new \PKP\form\validation\FormValidatorPost($this));
        $this->addCheck(new \PKP\form\validation\FormValidatorCSRF($this));

        // action links for pub id reset requests
        $pubIdPluginHelper = new PKPPubIdPluginHelper();
        $pubIdPluginHelper->setLinkActions($this->getContextId(), $this, $pubObject);
    }

    /**
     * @copydoc Form::fetch()
     *
     * @param null|mixed $template
     */
    public function fetch($request, $template = null, $display = false)
    {
        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign([
            'pubIdPlugins' => PluginRegistry::loadCategory('pubIds', true, $this->getContextId()),
            'pubObject' => $this->getPubObject(),
            'stageId' => $this->getStageId(),
            'formParams' => $this->getFormParams(),
        ]);
        if (is_a($this->getPubObject(), 'Representation') || is_a($this->getPubObject(), 'Chapter')) {
            $publicationId = $this->getPubObject()->getData('publicationId');
            $publication = Services::get('publication')->get($publicationId);
            $templateMgr->assign([
                'submissionId' => $publication->getData('submissionId'),
            ]);
        }
        // consider JavaScripts
        $pubIdPluginHelper = new PKPPubIdPluginHelper();
        $pubIdPluginHelper->addJavaScripts($this->getContextId(), $request, $templateMgr);
        return parent::fetch($request, $template, $display);
    }

    /**
     * @copydoc Form::initData()
     */
    public function initData()
    {
        $pubObject = $this->getPubObject();
        $this->setData('publisherId', $pubObject->getStoredPubId('publisher-id'));
        $pubIdPluginHelper = new PKPPubIdPluginHelper();
        $pubIdPluginHelper->init($this->getContextId(), $this, $pubObject);
        return parent::initData();
    }


    //
    // Getters
    //
    /**
     * Get the pub object
     *
     * @return object
     */
    public function getPubObject()
    {
        return $this->_pubObject;
    }

    /**
     * Get the stage id
     *
     * @return integer WORKFLOW_STAGE_ID_
     */
    public function getStageId()
    {
        return $this->_stageId;
    }

    /**
     * Get the context id
     *
     * @return integer
     */
    public function getContextId()
    {
        return $this->_contextId;
    }

    /**
     * Get the extra form parameters.
     *
     * @return array
     */
    public function getFormParams()
    {
        return $this->_formParams;
    }


    //
    // Form methods
    //
    /**
     * @copydoc Form::readInputData()
     */
    public function readInputData()
    {
        $this->readUserVars(['publisherId']);
        $pubIdPluginHelper = new PKPPubIdPluginHelper();
        $pubIdPluginHelper->readInputData($this->getContextId(), $this);
    }

    /**
     * @copydoc Form::validate()
     */
    public function validate($callHooks = true)
    {
        $pubObject = $this->getPubObject();
        $assocType = $this->getAssocType($pubObject);
        $publisherId = $this->getData('publisherId');
        $pubObjectId = $pubObject->getId();
        if ($assocType == ASSOC_TYPE_SUBMISSION_FILE) {
            $pubObjectId = $pubObject->getId();
        }
        $contextDao = Application::getContextDAO();
        if ($publisherId) {
            if (ctype_digit((string) $publisherId)) {
                $this->addError('publisherId', __('editor.publicIdentificationNumericNotAllowed', ['publicIdentifier' => $publisherId]));
                $this->addErrorField('$publisherId');
            } elseif (count(explode('/', $publisherId)) > 1) {
                $this->addError('publisherId', __('editor.publicIdentificationPatternNotAllowed', ['pattern' => '"/"']));
                $this->addErrorField('$publisherId');
            } elseif (is_a($pubObject, 'SubmissionFile') && preg_match('/^(\d+)-(\d+)$/', $publisherId)) {
                $this->addError('publisherId', __('editor.publicIdentificationPatternNotAllowed', ['pattern' => '\'/^(\d+)-(\d+)$/\' i.e. \'number-number\'']));
                $this->addErrorField('$publisherId');
            } elseif ($contextDao->anyPubIdExists($this->getContextId(), 'publisher-id', $publisherId, $assocType, $pubObjectId, true)) {
                $this->addError('publisherId', __('editor.publicIdentificationExistsForTheSameType', ['publicIdentifier' => $publisherId]));
                $this->addErrorField('$publisherId');
            }
        }
        $pubIdPluginHelper = new PKPPubIdPluginHelper();
        $pubIdPluginHelper->validate($this->getContextId(), $this, $this->getPubObject());
        return parent::validate($callHooks);
    }

    /**
     * Store objects with pub ids.
     *
     * @copydoc Form::execute()
     */
    public function execute(...$functionArgs)
    {
        parent::execute(...$functionArgs);

        $pubObject = $this->getPubObject();
        $pubObject->setStoredPubId('publisher-id', $this->getData('publisherId'));

        $pubIdPluginHelper = new PKPPubIdPluginHelper();
        $pubIdPluginHelper->execute($this->getContextId(), $this, $pubObject);

        if (is_a($pubObject, 'Representation')) {
            $representationDao = Application::getRepresentationDAO();
            $representationDao->updateObject($pubObject);
        } elseif (is_a($pubObject, 'SubmissionFile')) {
            $submissionFileDao = DAORegistry::getDAO('SubmissionFileDAO'); /** @var SubmissionFileDAO $submissionFileDao */
            $submissionFileDao->updateObject($pubObject);
        }
    }

    /**
     * Clear pub id.
     *
     * @param $pubIdPlugInClassName string
     */
    public function clearPubId($pubIdPlugInClassName)
    {
        $pubIdPluginHelper = new PKPPubIdPluginHelper();
        $pubIdPluginHelper->clearPubId($this->getContextId(), $pubIdPlugInClassName, $this->getPubObject());
    }

    /**
     * Get assoc type of the given object.
     *
     * @param $pubObject
     *
     * @return integer ASSOC_TYPE_
     */
    public function getAssocType($pubObject)
    {
        $assocType = null;
        if (is_a($pubObject, 'Submission')) {
            $assocType = ASSOC_TYPE_SUBMISSION;
        } elseif (is_a($pubObject, 'Publication')) {
            $assocType = ASSOC_TYPE_PUBLICATION;
        } elseif (is_a($pubObject, 'Representation')) {
            $assocType = ASSOC_TYPE_REPRESENTATION;
        } elseif (is_a($pubObject, 'SubmissionFile')) {
            $assocType = ASSOC_TYPE_SUBMISSION_FILE;
        }
        return $assocType;
    }
}
