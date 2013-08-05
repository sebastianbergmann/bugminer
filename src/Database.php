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

use SQLite3;

/**
 * @author    Sebastian Bergmann <sebastian@phpunit.de>
 * @copyright 2007-2013 Sebastian Bergmann <sebastian@phpunit.de>
 * @license   http://www.opensource.org/licenses/BSD-3-Clause  The BSD 3-Clause License
 * @link      http://github.com/sebastianbergmann/bugminer/tree
 * @since     Class available since Release 1.0.0
 */
class Database
{
    const SCHEMA = '
CREATE TABLE IF NOT EXISTS revisions(
  revision_id INTEGER PRIMARY KEY AUTOINCREMENT,
  revision    STRING
);

CREATE TABLE IF NOT EXISTS bugs(
  bug_id      INTEGER,
  revision_id INTEGER
);

CREATE UNIQUE INDEX IF NOT EXISTS bug_id_revision_id ON bugs (bug_id, revision_id);

CREATE TABLE IF NOT EXISTS files(
  file_id INTEGER PRIMARY KEY AUTOINCREMENT,
  file    STRING
);

CREATE TABLE IF NOT EXISTS file_changes(
  file_id     INTEGER,
  revision_id INTEGER
);

CREATE UNIQUE INDEX IF NOT EXISTS file_id_revision_id ON file_changes (file_id, revision_id);

CREATE TABLE IF NOT EXISTS functions(
  function_id INTEGER PRIMARY KEY AUTOINCREMENT,
  function    STRING
);

CREATE TABLE IF NOT EXISTS function_changes(
  function_id  INTEGER,
  revision_id  INTEGER
);

CREATE UNIQUE INDEX IF NOT EXISTS function_id_revision_id ON function_changes (function_id, revision_id);

CREATE VIEW IF NOT EXISTS bug_prone_functions AS
SELECT function_id, function, COUNT(*) AS function_count
  FROM functions
  JOIN function_changes USING (function_id)
  JOIN bugs             USING (revision_id)
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
  JOIN function_changes c2 ON c1.revision_id = c2.revision_id
   AND c1.function_id != c2.function_id
  JOIN functions f1 ON c1.function_id = f1.function_id
  JOIN functions f2 ON c2.function_id = f2.function_id
 GROUP BY changed_function, co_changed_function
 ORDER BY changed_function ASC,
          co_changed_function_count DESC;

CREATE VIEW IF NOT EXISTS bug_prone_files AS
SELECT file_id, file, COUNT(*) AS file_count
  FROM files
  JOIN file_changes USING (file_id)
  JOIN bugs         USING (revision_id)
 GROUP BY file_id
 ORDER BY file_count DESC;

CREATE VIEW IF NOT EXISTS frequently_changed_files AS
SELECT file_id, file, COUNT(*) AS file_count
  FROM files
  JOIN file_changes USING (file_id)
 GROUP BY file_id
 ORDER BY file_count DESC;

CREATE VIEW IF NOT EXISTS co_changed_files AS
SELECT p1.file  AS changed_file,
       p2.file  AS co_changed_file,
       COUNT(*) AS co_changed_file_count
  FROM file_changes c1
  JOIN file_changes c2 ON c1.revision_id = c2.revision_id
   AND c1.file_id != c2.file_id
  JOIN files p1 ON c1.file_id = p1.file_id
  JOIN files p2 ON c2.file_id = p2.file_id
 GROUP BY changed_file, co_changed_file
 ORDER BY changed_file ASC,
          co_changed_file_count DESC;';

    private $db;

    public function __construct($filename)
    {
        $create = false;

        if (!file_exists($filename)) {
            $create = true;
        }

        $this->db = new SQLite3($filename);

        if ($create) {
            $this->db->exec(self::SCHEMA);
        }
    }

    public function addRevision($sha1, array $changedFiles, array $changedFunctions, $bugfix)
    {
        $revisionId = $this->getid('revisions', 'revision', $sha1);

        if (is_numeric($bugfix)) {
            $this->db->exec(
                sprintf(
                    'INSERT INTO bugs (bug_id, revision_id) VALUES (%d, %d);',
                    $bugfix,
                    $revisionId
                )
            );
        }

        foreach ($changedFiles as $file) {
            $id = $this->getId('files', 'file', $file);

            $this->db->exec(
                sprintf(
                    'INSERT INTO file_changes (file_id, revision_id) VALUES (%d, %d);',
                    $id,
                    $revisionId
                )
            );
        }

        foreach ($changedFunctions as $function) {
            $id = $this->getId('functions', 'function', $function);

            $this->db->exec(
                sprintf(
                    'INSERT INTO function_changes (function_id, revision_id) VALUES (%d, %d);',
                    $id,
                    $revisionId
                )
            );
        }
    }

    private function getId($table, $column, $value)
    {
        $result = $this->db->querySingle(
            sprintf(
                'SELECT %s_id FROM %s WHERE %s="%s";',
                $column,
                $table,
                $column,
                $value
            )
        );

        if ($result !== null) {
            return $result;
        }

        $this->db->exec(
            sprintf(
                'INSERT INTO %s (%s) VALUES ("%s");',
                $table,
                $column,
                $value
            )
        );

        return $this->db->lastInsertRowID();
    }
}
