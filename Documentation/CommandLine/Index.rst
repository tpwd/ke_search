.. include:: /Includes.rst.txt

.. _commandline:

==================
Command line tools
==================

Certain ke_search functions can be accessed via the command line.

Run `vendor/bin/typo3 ke_search` to see a list of available commands.

.. _commandline-indexing:

Start the indexer
=================

Starts the indexer. All indexer configurations will be processed. Since version
5.4.0 you'll see a visual progression of the indexing process. Add this command
in the TYPO3 scheduler to run the indexer automatically (via "Execute console
commands").

.. code-block:: bash

	vendor/bin/typo3 ke_search:indexing

Parameters:

.. code-block:: bash

    -m, --indexingMode[=INDEXINGMODE]  Indexing mode, either "full" (default) or "incremental". [default: "full"]

Clear the index
===============

Clears all records from the index (truncates the table).

.. code-block:: bash

	vendor/bin/typo3 ke_search:clearindex

Remove the indexer lock
=======================

Removes the lock which prevents the indexer from running multiple times
at the same time.

.. code-block:: bash

	vendor/bin/typo3 ke_search:removelock

Show the status of the indexer
==============================

(since version 5.4.0)

Shows the status of the indexer and if the indexer is
currently running also the progress. If "mode" is set to "short", it will only
show "running" or "idle".

.. code-block:: bash

	vendor/bin/typo3 ke_search:indexerstatus

Parameters

.. code-block:: bash

    -m, --mode[=MODE]     Mode, either "full" (default) or "short". [default: "full"]
