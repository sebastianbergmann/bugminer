<?php
/*
 * This file is part of the Bugminer package.
 *
 * (c) Sebastian Bergmann <sebastian@phpunit.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SebastianBergmann\BugMiner;

use SebastianBergmann\Diff\Line;
use SebastianBergmann\Diff\Parser;
use SebastianBergmann\FinderFacade\FinderFacade;
use SebastianBergmann\Git;
use Symfony\Component\Console\Helper\ProgressHelper;
use Symfony\Component\Console\Output\OutputInterface;
use PHP_Token_Stream;

/**
 * @since Class available since Release 1.0.0
 */
class Processor
{
    private $db;
    private $finder;
    private $output;
    private $progressHelper;
    private $repository;

    public function __construct($repository, Database $db, FinderFacade $finder, OutputInterface $output, ProgressHelper $progressHelper = null)
    {
        $this->repository     = $repository;
        $this->db             = $db;
        $this->finder         = $finder;
        $this->output         = $output;
        $this->progressHelper = $progressHelper;
    }

    public function process()
    {
        $git           = new Git($this->repository);
        $parser        = new Parser;
        $currentBranch = $git->getCurrentBranch();
        $revisions     = $git->getRevisions();
        $count         = count($revisions);

        if ($count < 3) {
            return;
        }

        if ($this->progressHelper !== null) {
            $this->progressHelper->start($this->output, count($revisions) - 2);
        }

        for ($i = 1; $i < $count - 1; $i++) {
            $diff = $parser->parse(
                $git->getDiff(
                    $revisions[$i - 1]['sha1'],
                    $revisions[$i]['sha1']
                )
            );

            $git->checkout($revisions[$i]['sha1']);

            $this->processRevision(
                $revisions[$i]['sha1'],
                $revisions[$i]['message'],
                $diff,
                $this->findFiles()
            );

            if ($this->progressHelper !== null) {
                $this->progressHelper->advance();
            }
        }

        $git->checkout($currentBranch);
    }

    private function processRevision($sha1, $message, array $diff, array $files)
    {
        $files            = array_flip($files);
        $bugfix           = false;
        $changedFiles     = array();
        $changedFunctions = array();

        if (preg_match('/(Close|Closes|Fix|Fixes) #([0-9]*)/i', $message, $matches)) {
            $bugfix = $matches[2];
        }

        foreach ($diff as $_diff) {
            $file = substr($_diff->getFrom(), 2);

            if (!isset($files[$file])) {
                continue;
            }

            $changedFiles[] = $file;

            $ts = new PHP_Token_Stream($this->repository . '/' . $file);

            foreach ($_diff->getChunks() as $chunk) {
                $lineNr = $chunk->getStart();

                foreach ($chunk->getLines() as $line) {
                    if ($line->getType() != Line::UNCHANGED) {
                        $function = $ts->getFunctionForLine($lineNr);

                        if ($function !== null &&
                            $function != 'anonymous function') {
                            $changedFunctions[] = $function;
                        }
                    }

                    if ($line->getType() != Line::REMOVED) {
                        $lineNr++;
                    }
                }
            }
        }

        $this->db->addRevision(
            $sha1,
            $changedFiles,
            array_unique($changedFunctions),
            $bugfix
        );
    }

    private function findFiles()
    {
        $repository = $this->repository;

        return array_map(
            function ($v) use ($repository) {
                return str_replace($repository . '/', '', $v);
            },
            $this->finder->findFiles()
        );
    }
}
