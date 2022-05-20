.. include:: /Includes.rst.txt

.. _commandline:

==================
Command line tools
==================

Certain ke_search functions can be accessed via the command line.

.. _commandline-indexing:

Start the indexer
=================

.. code-block:: bash

	vendor/bin/typo3 ke_search:indexing

.. figure:: /Images/CommandLine/cli-start-indexing.png
   :alt: Indexing command
   :class: with-border

Clear the index
===============

.. code-block:: bash

	vendor/bin/typo3 ke_search:clearindex

.. figure:: /Images/CommandLine/cli-clear-index.png
   :alt: Clear index command
   :class: with-border

Remove the indexer lock
=======================

.. code-block:: bash

	vendor/bin/typo3 ke_search:removelock

.. figure:: /Images/CommandLine/cli-removelock.png
   :alt: Remove lock command
   :class: with-border
