<?php

namespace Tpwd\KeSearch\Indexer\Filetypes;

/* * *************************************************************
 *  Copyright notice
 *
 *  (c) 2011 Stefan Froemken
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 * ************************************************************* */

use Tpwd\KeSearch\Indexer\IndexerRunner;
use Tpwd\KeSearch\Indexer\Types\File;
use Tpwd\KeSearch\Lib\Fileinfo;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Utility\CommandUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Plugin 'Faceted search' for the 'ke_search' extension.
 * @author    Stefan Froemken
 */
class Pdf extends File implements FileIndexerInterface
{
    public array $extConf = [];
    public array $app = []; // saves the path to the executables
    public bool $isAppArraySet = false;

    /** @var IndexerRunner */
    public $pObj;

    /**
     * class constructor
     *
     * @param IndexerRunner $pObj
     */
    public function __construct($pObj)
    {
        $this->pObj = $pObj;

        // get extension configuration of ke_search_hooks
        $this->extConf = GeneralUtility::makeInstance(ExtensionConfiguration::class)->get('ke_search');

        // check if needed system tools pdftotext and pdfinfo exist
        if ($this->extConf['pathPdftotext'] ?? '') {
            $pathPdftotext = rtrim($this->extConf['pathPdftotext'], '/') . '/';
            $pathPdfinfo = rtrim($this->extConf['pathPdfinfo'], '/') . '/';

            $exe = Environment::isWindows() ? '.exe' : '';
            if ((is_executable($pathPdftotext . 'pdftotext' . $exe)
                && is_executable($pathPdfinfo . 'pdfinfo' . $exe))
            ) {
                $this->app['pdfinfo'] = $pathPdfinfo . 'pdfinfo' . $exe;
                $this->app['pdftotext'] = $pathPdftotext . 'pdftotext' . $exe;
                $this->isAppArraySet = true;
            } else {
                $this->isAppArraySet = false;
            }
        } else {
            $this->isAppArraySet = false;
        }

        if (!$this->isAppArraySet) {
            $errorMessage = 'The path to pdftools is not correctly set in the '
                . 'extension configuration. You can get the path with "which pdfinfo" or "which pdftotext".';
            // @extensionScannerIgnoreLine
            $pObj->logger->error($errorMessage);
            $this->addError($errorMessage);
        }
    }

    /**
     * get Content of PDF file
     * @param string $file
     * @return string The extracted content of the file
     */
    public function getContent($file)
    {
        $this->fileInfo = GeneralUtility::makeInstance(Fileinfo::class);
        $this->fileInfo->setFile($file);

        // get PDF informations
        $pdfInfo = $this->getPdfInfo($file);
        if (empty($pdfInfo)) {
            return '';
        }

        // proceed only of there are any pages found
        if (isset($pdfInfo['pages']) && (int)$pdfInfo['pages'] && $this->isAppArraySet) {
            // create the tempfile which will contain the content
            $tempFileName = GeneralUtility::tempnam('pdf_files-Indexer');

            // Delete if exists, just to be safe.
            @unlink($tempFileName);

            // generate and execute the pdftotext commandline tool
            $fileEscaped = CommandUtility::escapeShellArgument($file);
            $cmd = "{$this->app['pdftotext']} -enc UTF-8 $fileEscaped $tempFileName 2>&1";

            CommandUtility::exec($cmd, $output);

            if (is_array($output) && count($output)) {
                $errorMessage =
                    'There have been problems while extracting the content for PDF file '
                    . $file . '. Output from pdftotext: ' . json_encode($output);
                // @extensionScannerIgnoreLine
                $this->pObj->logger->error($errorMessage);
                $this->addError($errorMessage);
            }

            // check if the tempFile was successfully created
            if (@is_file($tempFileName)) {
                $content = GeneralUtility::getUrl($tempFileName);
                unlink($tempFileName);
            } else {
                $errorMessage = 'Content for file ' . $file . ' could not be extracted. Maybe it is encrypted?';
                $this->pObj->logger->warning($errorMessage);
                $this->addError($errorMessage);

                // return empty string if no content was found
                $content = '';
            }
            // sanitize content
            $content = $this->removeReplacementChar($content);

            return $this->removeEndJunk($content);
        }
        $errorMessage =
            'Could not find pages for file ' . $file
            . '. Maybe it is not a PDF file? Messages from pdftotext: ' . json_encode($pdfInfo);
        // @extensionScannerIgnoreLine
        $this->pObj->logger->error($errorMessage);
        $this->addError($errorMessage);

        return '';
    }

    /**
     * execute commandline tool pdfinfo to extract pdf informations from file
     * @param string $file
     * @return array The pdf informations as array
     */
    public function getPdfInfo($file)
    {
        if ($this->fileInfo->getIsFile()
            && $this->fileInfo->getExtension() == 'pdf'
            && $this->isAppArraySet
        ) {
            $fileEscaped = CommandUtility::escapeShellArgument($file);
            $cmd = "{$this->app['pdfinfo']} $fileEscaped 2>&1";
            CommandUtility::exec($cmd, $pdfInfoArray);
            $pdfInfo = $this->splitPdfInfo($pdfInfoArray);

            return $pdfInfo;
        }

        return [];
    }

    /**
     * Transform PDF info into a usable format.
     *
     * @param array $pdfInfoArray Data of PDF content, coming from the pdfinfo tool
     * @return array The pdf information as array in a usable format
     */
    public function splitPdfInfo($pdfInfoArray)
    {
        $res = [];
        if (is_array($pdfInfoArray)) {
            foreach ($pdfInfoArray as $line) {
                $parts = explode(':', $line, 2);
                if (count($parts) > 1 && trim($parts[0])) {
                    $key = strtolower(trim($parts[0]));
                    $newKey = $key;
                    $i = 1;
                    while (array_key_exists($newKey, $res)) {
                        $newKey = $key . $i;
                        $i++;
                    }
                    $res[$newKey] = trim($parts[1]);
                }
            }
        }
        return $res;
    }

    /**
     * Removes some strange char(12) characters and line breaks that then to
     * occur in the end of the string from external files.
     * @param string $string String to clean up
     * @return string Cleaned up string
     */
    public function removeEndJunk(string $string): string
    {
        return trim(preg_replace('/[' . chr(10) . chr(12) . ']*$/', '', $string));
    }

    /**
     * Remove (U+FFFD)� characters due to incorrect image indexing in PDF file
     * @param string $string String to clean up
     * @return string Cleaned up string
     */
    public function removeReplacementChar(string $string): string
    {
        return trim(preg_replace('@\x{FFFD}@u', '', $string));
    }
}
