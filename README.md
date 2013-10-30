# BugMiner

## History and Status

In 2007 I attended an Open Source Jam at the Google office in Zurich. On my
way there I read [this paper](https://ieeexplore.ieee.org/xpl/articleDetails.jsp?arnumber=1463228)
and got an idea that I started hacking on right away. After all, train rides
are usually boring. By the time the Open Source Jam started I had a working
script that could analyse the history of a PHP project in a Subversion
repository and report the files that are most commonly changed in bug fix
commits.

When Chris Shiflett asked me to write an article for his PHP Advent series later
that year I remembered this script and wrote about it. That article is still
available [here](http://shiflett.org/blog/2007/dec/php-advent-calendar-day-3).

One night in 2009 during an interesting discussion with Boris Erdmann and
Kristian Köhntopp I remembered this script again. I wanted to expand on its
idea and store the data in a relational database to perform data mining using
SQL. That night we developed an initial schema along with some first views that
perform useful operations on the collected data.

Not much happened since then. In August 2013 I remembered the tool yet again and
was surprised that I had committed (not really something of value) to its Git
repository over the years. I dusted off the code (of which there was not much)
and there is now a working CLI tool that can iterate over all revisions of a Git
repository, collect some data, and store the data collected in an SQLite3
database.

Maybe if I get bored again I will get around to work on this tool once more ...
