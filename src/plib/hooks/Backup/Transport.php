<?php
// Copyright 1999-2018. Plesk International GmbH. All rights reserved.

class Modules_CloudBackupExample_Backup_Transport extends pm_Hook_Backup_Transport
{
    /** @var Modules_CloudBackupExample_Settings */
    private $settings;

    /**
     * Initializes an object of the class. The method parent::init() must be called.
     * @param string $objectType (server, reseller, customer, subscription)
     * @param int|null $objectId
     */
    public function init($objectType, $objectId)
    {
        parent::init($objectType, $objectId);
        $this->settings = new Modules_CloudBackupExample_Settings($objectType, $objectId);
    }

    /**
     * Returns true if the extension is configured
     * @return bool
     */
    public function isConfigured()
    {
        return $this->settings->isConfigured();
    }

    /**
     * Returns detailed storage description (name, internal path etc.)
     * @return string
     */
    public function getDescription()
    {
        return pm_Session::getClient()->isAdmin()
            ? pm_Locale::lmsg('transport.description', ['path' => $this->settings->getFullPath()])
            : pm_Locale::lmsg('transport.descriptionShort');
    }

    /**
     * Returns array ['name' => ..., 'size' => ..., 'isDir' => ...]
     * @param string $path
     * @return null|array
     */
    public function stat($path)
    {
        pm_Log::debug('Stat the file ' . $path);

        $fullPath = $this->settings->getFullPath($path);
        if (!file_exists($fullPath)) {
            return null;
        }
        $fileInfo = new SplFileInfo($fullPath);
        return [
            'name' => $fileInfo->getBasename(),
            'size' => $fileInfo->getSize(),
            'isDir' => $fileInfo->isDir(),
        ];
    }

    /**
     * Returns array [['name' => ..., 'size' => ..., 'isDir' => ...], ...]
     * @param $path
     * @return array
     */
    public function listDir($path)
    {
        pm_Log::debug('List the directory ' . $path);

        $result = [];
        $dirItems = new DirectoryIterator($this->settings->getFullPath($path));
        foreach ($dirItems as $dirItem) {
            if ($dirItem->isDot()) {
                continue;
            }
            $result[] = [
                'name' => $dirItem->getBasename(),
                'size' => $dirItem->getSize(),
                'isDir' => $dirItem->isDir(),
            ];
        }
        return $result;
    }

    /**
     * Creates the specified directory
     * @param string $path
     * @throws pm_Exception
     */
    public function createDir($path)
    {
        pm_Log::debug('Create the directory ' . $path);

        if (!mkdir($this->settings->getFullPath($path), 0700, true)) {
            throw new pm_Exception(pm_Locale::lmsg('transport.createDirError', ['path' => $this->settings->getFullPath($path)]));
        }
    }

    /**
     * Removes the specified file
     * @param string $path
     * @throws pm_Exception
     */
    public function deleteFile($path)
    {
        pm_Log::debug('Delete the file ' . $path);

        if (!unlink($this->settings->getFullPath($path))) {
            throw new pm_Exception(pm_Locale::lmsg('transport.deleteFileError', ['path' => $this->settings->getFullPath($path)]));
        }
    }

    /**
     * Removes the specified directory
     * @param string $path
     * @throws pm_Exception
     */
    public function deleteDir($path)
    {
        pm_Log::debug('Delete the directory ' . $path);

        if (!rmdir($this->settings->getFullPath($path))) {
            throw new pm_Exception(pm_Locale::lmsg('transport.deleteDirError', ['path' => $this->settings->getFullPath($path)]));
        }
    }

    /**
     * Returns size of read buffer preferred for the extension
     * @return int
     */
    public function getReadBufferSize()
    {
        return 100 * 1024 * 1024;
    }

    /**
     * Opens file for read from the specified offset
     *
     * @param string $path
     * @param int $offset
     * @return mixed Returns file descriptor for appendFile
     * @throws pm_Exception
     */
    public function openFileRead($path, $offset = 0)
    {
        pm_Log::debug('Open the file ' . $path . ' to read with offset ' . $offset);

        $handle = fopen($this->settings->getFullPath($path), 'rb');
        if (false === $handle) {
            throw new pm_Exception(pm_Locale::lmsg(
                'transport.openFileError'
                , ['path' => $this->settings->getFullPath($path)]));
        }
        if (fseek($handle, $offset) != 0) {
            throw new pm_Exception(pm_Locale::lmsg(
                'transport.seekFileError'
                , ['path' => $this->settings->getFullPath($path), 'offset' => $offset]));
        }
        return $handle;
    }

    /**
     * Reads file content to the local file
     *
     * @param mixed $handle
     * @param string $localFile
     * @param int|null $size if the size is null the whole file should be read
     * @return int Bytes read
     * @throws pm_Exception
     */
    public function readFile($handle, $localFile, $size = null)
    {
        pm_Log::debug('Read to ' . $localFile . '. Bytes ' . (is_null($size) ? '-' : strval($size)));

        $localFileHandle = fopen($localFile, 'wb');
        if (false === $localFileHandle) {
            throw new pm_Exception(pm_Locale::lmsg('transport.openFileError', ['path' => $localFile]));
        }

        $sizeTotalRead = 0;
        try {
            $bufSize = 10 * 1024 * 1024;
            $sizeToRead = (null !== $size && $size < $bufSize) ? $size : $bufSize;
            while ($sizeToRead > 0 && !feof($handle)) {
                $content = fread($handle, $sizeToRead);
                if (false === $content) {
                    throw new pm_Exception(pm_Locale::lmsg('transport.readFileError'));
                }
                if (false === fwrite($localFileHandle, $content)) {
                    throw new pm_Exception(pm_Locale::lmsg('transport.writeLocalFileError', ['path' => $localFile]));
                }
                $sizeTotalRead += strlen($content);
                $sizeToRead = (null !== $size && ($size - $sizeTotalRead) < $bufSize) ? $size - $sizeTotalRead : $bufSize;
            }
        } finally {
            fclose($localFileHandle);
        }

        return $sizeTotalRead;
    }

    /**
     * Returns size of write buffer preferred for the extension
     *
     * @return int
     */
    public function getWriteBufferSize()
    {
        return 100 * 1024 * 1024;
    }

    /**
     * Opens file for appending data to the end of the file
     *
     * @param string $path
     * @return mixed Returns file descriptor for appendFile
     * @throws pm_Exception
     */
    public function openFileWrite($path)
    {
        pm_Log::debug('Open file ' . $path . ' to write');

        $handle = fopen($this->settings->getFullPath($path), 'ab');
        if (false === $handle) {
            throw new pm_Exception(pm_Locale::lmsg(
                'transport.openFileError'
                , ['path' => $this->settings->getFullPath($path)]));
        }
        return $handle;
    }

    /**
     * Appends content of the local file to the file in the external storage
     *
     * @param mixed $handle
     * @param string $localFile
     * @return int Bytes written
     * @throws pm_Exception
     */
    public function appendFile($handle, $localFile)
    {
        pm_Log::debug('Append file ' . $localFile);

        $localFileHandle = fopen($localFile, 'rb');
        if (false === $localFileHandle) {
            throw new pm_Exception(pm_Locale::lmsg('transport.openFileError', ['path' => $localFile]));
        }

        $sizeTotalWritten = 0;
        try {
            $bufSize = 10 * 1024 * 1024;
            while (!feof($localFileHandle)) {
                $content = fread($localFileHandle, $bufSize);
                if (false === $content) {
                    throw new pm_Exception(pm_Locale::lmsg('transport.readLocalFileError', ['path' => $localFile]));
                }
                if (false === fwrite($handle, $content)) {
                    throw new pm_Exception(pm_Locale::lmsg('transport.writeFileError'));
                }
                $sizeTotalWritten += strlen($content);
            }
        } finally {
            fclose($localFileHandle);
        }

        return $sizeTotalWritten;
    }

    /**
     * Closes file
     *
     * @param mixed $handle
     */
    public function closeFile($handle)
    {
        pm_Log::debug('Close the file');

        fclose($handle);
    }

    /**
     * Returns subform with storage settings
     *
     * @return pm_Form_SubForm
     */
    public function getSettingsSubForm()
    {
        return new Modules_CloudBackupExample_SettingsSubForm(['context' => [
            'objectType' => $this->_objectType,
            'objectId' => $this->_objectId,
        ]]);
    }
}
