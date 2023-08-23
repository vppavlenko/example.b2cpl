<?php
namespace WS\B2CplApi;

use DateTime;
use FilesystemIterator;

/**
 * Class LogResponse
 * @package WS\B2CplApi
 */
class LogResponse {

    private $dirName;
    private $documentRoot;

    /**
     * LogResponse constructor.
     * @param $dirName
     */
    public function __construct($dirName) {
        $this->dirName = $dirName;
        $this->documentRoot = $_SERVER['DOCUMENT_ROOT'];
    }

    public function saveResponseToFile($response) {

        $this->deleteOldFiles();

        $fileName = "log_" . date('d_m_Y_H_i_s') . ".txt";
        $tmpFilePath = $this->documentRoot . '/upload/' . $this->dirName;

        if (!file_exists($tmpFilePath)) {
            mkdir($tmpFilePath);
        }
        $tmpFilePath = $tmpFilePath . '/' . $fileName;
        file_put_contents($tmpFilePath, $response);
    }

    private function deleteOldFiles() {
        $scanDirPath = $this->documentRoot . '/upload/' . $this->dirName ;
        $iterator = new FilesystemIterator($scanDirPath);
        foreach($iterator as $entry) {
            if ($this->isOldFile($entry->getFilename())) {
                unlink($entry->getPathname());
            }
        }
    }

    private function isOldFile($getFilename) {
        $arDateTime = explode('_', str_replace(array("log_", ".txt"), "", $getFilename));
        $formatDate = "".$arDateTime[2]."-".$arDateTime[1]."-".$arDateTime[0]." ".$arDateTime[3].":".$arDateTime[4].":".$arDateTime[5]."";
        $curDate = new DateTime($formatDate);
        $diffDate = new DateTime();
        $diffDate = $diffDate->modify('-3 days');
        if ($curDate < $diffDate){
            return true;
        }
        return false;
    }
}
