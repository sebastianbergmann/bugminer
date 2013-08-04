<?php
/**
 * BugMiner
 *
 * Copyright (c) 2007-2013, Sebastian Bergmann <sebastian@phpunit.de>.
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions
 * are met:
 *
 *   * Redistributions of source code must retain the above copyright
 *     notice, this list of conditions and the following disclaimer.
 *
 *   * Redistributions in binary form must reproduce the above copyright
 *     notice, this list of conditions and the following disclaimer in
 *     the documentation and/or other materials provided with the
 *     distribution.
 *
 *   * Neither the name of Sebastian Bergmann nor the names of his
 *     contributors may be used to endorse or promote products derived
 *     from this software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS
 * FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT,
 * INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING,
 * BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
 * CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT
 * LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN
 * ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 *
 * @package   BugMiner
 * @author    Sebastian Bergmann <sebastian@phpunit.de>
 * @copyright 2007-2013 Sebastian Bergmann <sebastian@phpunit.de>
 * @license   http://www.opensource.org/licenses/BSD-3-Clause  The BSD 3-Clause License
 * @since     File available since Release 1.0.0
 */

namespace SebastianBergmann\BugMiner;

use SebastianBergmann\Diff\Parser;
use SebastianBergmann\FinderFacade\FinderFacade;
use SebastianBergmann\Git;
use Symfony\Component\Console\Helper\ProgressHelper;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @author    Sebastian Bergmann <sebastian@phpunit.de>
 * @copyright 2007-2013 Sebastian Bergmann <sebastian@phpunit.de>
 * @license   http://www.opensource.org/licenses/BSD-3-Clause  The BSD 3-Clause License
 * @link      http://github.com/sebastianbergmann/bugminer/tree
 * @since     Class available since Release 1.0.0
 */
class Processor
{
    private $db;
    private $finder;
    private $output;
    private $progressHelper;
    private $repository;

    public function __construct($repository, \SQLite3 $db, FinderFacade $finder, OutputInterface $output, ProgressHelper $progressHelper = null)
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

            $this->processRevision($diff, $this->findFiles());

            if ($this->progressHelper !== null) {
                $this->progressHelper->advance();
            }
        }

        $git->checkout($currentBranch);
    }

    private function processRevision(array $diff, array $files)
    {
        $files = array_flip($files);

        foreach ($diff as $_diff) {
            $file = substr($_diff->getFrom(), 2);

            if (!isset($files[$file])) {
                continue;
            }
        }
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
