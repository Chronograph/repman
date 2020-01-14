<?php

declare(strict_types=1);

namespace Buddy\Repman\Service\Dist\Storage;

use Buddy\Repman\Service\Dist;
use Buddy\Repman\Service\Dist\Storage;
use Buddy\Repman\Service\Downloader;
use Munus\Collection\GenericList;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

final class FileStorage implements Storage
{
    private string $distsDir;
    private Downloader $downloader;

    public function __construct(string $distsDir, Downloader $downloader)
    {
        $this->distsDir = $distsDir;
        $this->downloader = $downloader;
    }

    public function has(Dist $dist): bool
    {
        return is_readable($this->filename($dist));
    }

    public function download(string $url, Dist $dist): void
    {
        if ($this->has($dist)) {
            return;
        }

        $filename = $this->filename($dist);
        $this->ensureDirExist($filename);

        file_put_contents(
            $filename,
            $this->downloader->getContents($url)->getOrElseThrow(
                new \RuntimeException(sprintf('Failed to download %s from %s', $dist->package(), $url))
            )
        );
    }

    public function filename(Dist $dist): string
    {
        return sprintf(
            '%s/%s/dist/%s/%s_%s.%s',
            $this->distsDir,
            $dist->repo(),
            $dist->package(),
            $dist->version(),
            $dist->ref(),
            $dist->format()
        );
    }

    /**
     * @return GenericList<string>
     */
    public function packages(string $repo): GenericList
    {
        $dir = $this->distsDir.'/'.$repo.'/dist';
        if (!is_dir($dir)) {
            return GenericList::empty();
        }

        $files = Finder::create()->directories()->sortByName()->depth(1)->ignoreVCS(true)->in($dir);

        return GenericList::ofAll(array_map(
            fn (SplFileInfo $fileInfo) => $fileInfo->getRelativePathname(),
            iterator_to_array($files->getIterator()
            )));
    }

    private function ensureDirExist(string $filename): void
    {
        $dirname = dirname($filename);
        if (!is_dir($dirname)) {
            mkdir($dirname, 0777, true);
        }
    }
}