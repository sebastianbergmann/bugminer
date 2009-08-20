--
-- BugMiner
--
-- Copyright (c) 2007-2009, Sebastian Bergmann <sb@sebastian-bergmann.de>.
-- All rights reserved.
--
-- Redistribution and use in source and binary forms, with or without
-- modification, are permitted provided that the following conditions
-- are met:
--
--   * Redistributions of source code must retain the above copyright
--     notice, this list of conditions and the following disclaimer.
-- 
--   * Redistributions in binary form must reproduce the above copyright
--     notice, this list of conditions and the following disclaimer in
--     the documentation and/or other materials provided with the
--     distribution.
--
--   * Neither the name of Sebastian Bergmann nor the names of his
--     contributors may be used to endorse or promote products derived
--     from this software without specific prior written permission.
--
-- THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
-- "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
-- LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS
-- FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
-- COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT,
-- INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING,
-- BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
-- LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
-- CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT
-- LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN
-- ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
-- POSSIBILITY OF SUCH DAMAGE.
--

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
          co_changed_path_count DESC;
