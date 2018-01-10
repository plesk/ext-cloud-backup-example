<?php
// Copyright 1999-2018. Plesk International GmbH. All rights reserved.

class Modules_CloudBackupExample_SettingsSubForm extends pm_Form_SubForm
{
    /** @var bool  */
    protected $_autoInitContext = true;

    /** @var string */
    protected $_objectType;

    /** @var int|null */
    protected $_objectId;

    /** @var Modules_CloudBackupExample_Settings */
    protected $settings;

    public function init()
    {
        parent::init();

        $this->settings = new Modules_CloudBackupExample_Settings($this->_objectType, $this->_objectId);
        $description = pm_Session::getClient()->isAdmin()
            ? pm_Locale::lmsg('components.forms.settings.pathDesc', ['name' => $this->settings->getFullPath()])
            : '';
        $this->addElement('Text', 'path', [
            'label' => pm_Locale::lmsg('components.forms.settings.path'),
            'value' => $this->settings->path,
            'required' => false,
            'description' => $description,
            'filters' => [
                ['StringTrim', [' /\\']],
            ],
            'validators' => [
                ['Alnum'],
            ],
        ]);
    }

    public function process()
    {
        $this->settings->path = $this->getValue('path');

        // Local folder creation by means of file manager to make our sample transport
        // implementation able to read/write destination files.
        $fileManager = new pm_ServerFileManager();
        if (!$fileManager->fileExists($this->settings->getFullPath())) {
            $fileManager->mkdir($this->settings->getFullPath(), '0700', true);
        }

        $this->settings->save();
    }
}
