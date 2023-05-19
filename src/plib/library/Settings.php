<?php
// Copyright 1999-2018. Plesk International GmbH. All rights reserved.

/**
 * Class Modules_CloudBackupExample_Settings
 * @property string|null $path
 */
class Modules_CloudBackupExample_Settings
{
    const SETTINGS_NAME = 'settings';

    /** @var pm_Domain|pm_Client|null  */
    private $object;

    /** @var string */
    private $objectType;

    /** @var string|null */
    private $objectName;

    /** @var array */
    private $settings = [];

    /**
     * Modules_CloudBackupExample_Settings constructor.
     * @param string $objectType
     * @param int|null $objectId
     * @throws pm_Exception
     */
    public function __construct($objectType, $objectId)
    {
        $this->object = null;
        $this->objectType = $objectType;
        $this->objectName = null;
        switch ($objectType) {
            case pm_Hook_Backup_Transport::TYPE_SUBSCRIPTION:
                $this->object = new pm_Domain($objectId);
                $this->objectName = $this->object->getName();
                break;
            case pm_Hook_Backup_Transport::TYPE_CUSTOMER:
            case pm_Hook_Backup_Transport::TYPE_RESELLER:
                $this->object = pm_Client::getByClientId($objectId);
                $this->objectName = $this->object->getLogin();
                break;
        }
        $settings = null == $this->object
            ? json_decode(pm_Settings::get(self::SETTINGS_NAME, ''), true)
            : json_decode($this->object->getSetting(self::SETTINGS_NAME, ''), true);
        if (is_array($settings)) {
            $this->settings = $settings;
        }
    }

    /**
     * @param string $name
     * @return mixed|null
     */
    public function __get($name)
    {
        return $this->settings[$name] ?? null;
    }

    /**
     * @param string $name
     * @param mixed|null $value
     */
    public function __set($name, $value)
    {
        $this->settings[$name] = $value;
    }

    public function save()
    {
        if (null == $this->object) {
            pm_Settings::set(self::SETTINGS_NAME, json_encode($this->settings));
        } else {
            $this->object->setSetting(self::SETTINGS_NAME, json_encode($this->settings));
        }
    }

    /**
     * @return bool
     */
    public function isConfigured()
    {
        return true;
    }

    /**
     * @param string|null $path
     * @return string
     */
    public function getFullPath($path = null)
    {
        $parts = [
            rtrim(DUMPS_REPOSITORY_DIR, '/\\'),
            '.local',
            $this->objectType,
        ];
        if ($this->objectName) {
            $parts[] = $this->objectName;
        }
        if ($this->path) {
            $parts[] = $this->path;
        }
        $path = $path ? trim($path, '/\\') : $path;
        if ($path) {
            $parts[] = $path;
        }
        return join(DIRECTORY_SEPARATOR, $parts);
    }
}

