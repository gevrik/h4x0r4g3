<?php
/**
 * Composer update script.
 * Copies all composer assets to the correct directory for inclusion.
 */

namespace Scripts;

use Composer\Script\Event;

/**
 * Class Composer
 *
 * @package Script
 */
class Composer
{
    /**
     * Run post update commands
     *
     * @param Event $event
     */
    public static function postUpdate(Event $event)
    {
        $baseDir = getcwd() . '/public/assets/vendor';

        self::deleteDir($baseDir, 1);

        echo "Deleted files in " . $baseDir . "\n";

        self::copyFiles(
            $baseDir . '/bootstrap/',
            getcwd() . '/vendor/twbs/bootstrap/dist/*'
        );

        self::copyFiles(
            $baseDir . '/bootstrap3/',
            getcwd() . '/vendor/twitter/bootstrap/dist/*'
        );

        self::copyFiles(
            $baseDir . '/leaflet/',
            getcwd() . '/vendor/drmonty/leaflet/*'
        );

        self::copyFiles(
            $baseDir . '/datatables/',
            getcwd() . '/vendor/datatables/datatables/media/*'
        );

        self::copyFiles(
            $baseDir . '/jquery/',
            getcwd() . '/vendor/frameworks/jquery/*'
        );

        self::copyFiles(
            $baseDir . '/jqueryui/',
            getcwd() . '/vendor/components/jqueryui/*'
        );

        self::copyFiles(
            $baseDir . '/font-awesome/',
            getcwd() . '/vendor/fortawesome/font-awesome/*'
        );

        self::copyFiles(
            $baseDir . '/ckeditor/',
            getcwd() . '/vendor/ckeditor/ckeditor/*'
        );

        echo "Copied files from to " . $baseDir . "\n";
    }

    /**
     * Delete vendor assets
     *
     * @param $baseDir
     */
    public static function deleteDir($baseDir, $level)
    {
        if (!is_dir($baseDir)) {
            return;
        }

        $dirList = scandir($baseDir);

        foreach ($dirList as $currentDir) {
            $ignoreList = $level == 1
                ? ['.', '..', '.gitignore']
                : ['.', '..'];

            if (in_array($currentDir, $ignoreList)) {
                continue;
            }

            if (is_dir($baseDir . '/' . $currentDir)) {
                self::deleteDir($baseDir . '/' . $currentDir, $level + 1);

                rmdir($baseDir . '/' . $currentDir);
            } else {
                unlink($baseDir . '/' . $currentDir);
            }
        }
    }

    /**
     * Copy asset files
     *
     * @param $targetDir
     * @param $sourceDir
     */
    public static function copyFiles($targetDir, $sourceDir)
    {
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0777, true);
        }

        $dirList = glob($sourceDir);

        foreach ($dirList as $currentNode) {
            if (is_dir($currentNode)) {
                self::copyFiles(
                    $targetDir . basename($currentNode) . '/',
                    $currentNode . '/*'
                );
            } else {
                copy($currentNode, $targetDir . basename($currentNode));
            }
        }
    }
}
