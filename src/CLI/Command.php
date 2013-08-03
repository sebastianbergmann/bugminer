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

namespace SebastianBergmann\BugMiner\CLI;

use SebastianBergmann\BugMiner\Processor;
use SebastianBergmann\FinderFacade\FinderFacade;
use Symfony\Component\Console\Command\Command as AbstractCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @author    Sebastian Bergmann <sebastian@phpunit.de>
 * @copyright 2007-2013 Sebastian Bergmann <sebastian@phpunit.de>
 * @license   http://www.opensource.org/licenses/BSD-3-Clause  The BSD 3-Clause License
 * @link      http://github.com/sebastianbergmann/bugminer/tree
 * @since     Class available since Release 1.0.0
 */
class Command extends AbstractCommand
{
    const SCHEMA = '
CREATE TABLE IF NOT EXISTS bugs(
  bug_id   INTEGER,
  revision INTEGER
);

CREATE UNIQUE INDEX IF NOT EXISTS bug_id_revision ON bugs (bug_id, revision);

CREATE TABLE IF NOT EXISTS functions(
  function_id INTEGER PRIMARY KEY AUTOINCREMENT,
  function    STRING
);

CREATE TABLE IF NOT EXISTS function_changes(
  function_id  INTEGER,
  revision     INTEGER
);

CREATE UNIQUE INDEX IF NOT EXISTS function_id_revision ON function_changes (function_id, revision);

CREATE TABLE IF NOT EXISTS paths(
  path_id INTEGER PRIMARY KEY AUTOINCREMENT,
  path    STRING
);

CREATE TABLE IF NOT EXISTS path_changes(
  path_id  INTEGER,
  revision INTEGER
);

CREATE UNIQUE INDEX IF NOT EXISTS path_id_revision ON path_changes (path_id, revision);

CREATE VIEW IF NOT EXISTS bug_prone_functions AS
SELECT function_id, function, COUNT(*) AS function_count
  FROM functions
  JOIN function_changes USING (function_id)
  JOIN bugs             USING (revision)
 GROUP BY function_id
 ORDER BY function_count DESC;

CREATE VIEW IF NOT EXISTS frequently_changed_functions AS
SELECT function_id, function, COUNT(*) AS function_count
  FROM functions
  JOIN function_changes USING (function_id)
 GROUP BY function_id
 ORDER BY function_count DESC;

CREATE VIEW IF NOT EXISTS co_changed_functions AS
SELECT f1.function  AS changed_function,
       f2.function  AS co_changed_function,
       COUNT(*)     AS co_changed_function_count
  FROM function_changes c1
  JOIN function_changes c2 ON c1.revision = c2.revision
   AND c1.function_id != c2.function_id
  JOIN functions f1 ON c1.function_id = f1.function_id
  JOIN functions f2 ON c2.function_id = f2.function_id
 GROUP BY changed_function, co_changed_function
 ORDER BY changed_function ASC,
          co_changed_function_count DESC;

CREATE VIEW IF NOT EXISTS bug_prone_paths AS
SELECT path_id, path, COUNT(*) AS path_count
  FROM paths
  JOIN path_changes USING (path_id)
  JOIN bugs         USING (revision)
 GROUP BY path_id
 ORDER BY path_count DESC;

CREATE VIEW IF NOT EXISTS frequently_changed_paths AS
SELECT path_id, path, COUNT(*) AS path_count
  FROM paths
  JOIN path_changes USING (path_id)
 GROUP BY path_id
 ORDER BY path_count DESC;

CREATE VIEW IF NOT EXISTS co_changed_paths AS
SELECT p1.path  AS changed_path,
       p2.path  AS co_changed_path,
       COUNT(*) AS co_changed_path_count
  FROM path_changes c1
  JOIN path_changes c2 ON c1.revision = c2.revision
   AND c1.path_id != c2.path_id
  JOIN paths p1 ON c1.path_id = p1.path_id
  JOIN paths p2 ON c2.path_id = p2.path_id
 GROUP BY changed_path, co_changed_path
 ORDER BY changed_path ASC,
          co_changed_path_count DESC;';

    /**
     * Configures the current command.
     */
    protected function configure()
    {
        $this->setName('bugminer')
             ->addArgument(
                 'database',
                 InputArgument::REQUIRED,
                 'Path to the SQLite3 database'
             )
             ->addArgument(
                 'repository',
                 InputArgument::REQUIRED,
                 'Path to the Git repository'
             )
             ->addOption(
                 'names',
                 null,
                 InputOption::VALUE_REQUIRED,
                 'A comma-separated list of file names to check',
                 array('*.php')
             )
             ->addOption(
                 'names-exclude',
                 null,
                 InputOption::VALUE_REQUIRED,
                 'A comma-separated list of file names to exclude',
                 array()
             )
             ->addOption(
                 'exclude',
                 null,
                 InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                 'Exclude a directory from code analysis'
             )
             ->addOption(
                 'progress',
                 null,
                 InputOption::VALUE_NONE,
                 'Show progress bar'
             );
    }

    /**
     * Executes the current command.
     *
     * @param InputInterface  $input  An InputInterface instance
     * @param OutputInterface $output An OutputInterface instance
     *
     * @return null|integer null or 0 if everything went fine, or an error code
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $db         = $this->handleDatabase($input->getArgument('database'));
        $repository = $input->getArgument('repository');
        $quiet      = $output->getVerbosity() == OutputInterface::VERBOSITY_QUIET;

        $finder = new FinderFacade(
            array($repository),
            $input->getOption('exclude'),
            $this->handleCSVOption($input, 'names'),
            $this->handleCSVOption($input, 'names-exclude')
        );

        $progressHelper = null;

        if ($input->getOption('progress')) {
            $progressHelper = $this->getHelperSet()->get('progress');
        }

        $processor = new Processor(
            $repository,
            $db,
            $finder,
            $output,
            $progressHelper
        );

        $processor->process();

        if ($input->getOption('progress')) {
            $progressHelper->finish();
            $output->writeln('');
        }

        if (!$quiet) {
            $output->writeln(\PHP_Timer::resourceUsage());
        }
    }

    /**
     * @param  Symfony\Component\Console\Input\InputOption $input
     * @param  string                                      $option
     * @return array
     */
    private function handleCSVOption(InputInterface $input, $option)
    {
        $result = $input->getOption($option);

        if (!is_array($result)) {
            $result = explode(',', $result);
            array_map('trim', $result);
        }

        return $result;
    }

    /**
     * @param  string $filename
     * @return \SQLite3
     */
    private function handleDatabase($filename)
    {
        $create = false;

        if (!file_exists($filename)) {
            $create = true;
        }

        $db = new \SQLite3($filename);

        if ($create) {
            $db->exec(self::SCHEMA);
        }

        return $db;
    }
}
