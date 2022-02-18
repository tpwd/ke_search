.. include:: /Includes.rst.txt

.. _configuration-search-word-length:

==================
Search word length
==================

By default ke_search only finds words with a minimum length of four characters. This corresponds to the MySQL setting
`ft_min_word_len` which is set to `4` by default.

.. hint::
   As standard ke_search uses :ref:`MyISAM as storage engine <configuration-storage-engine-innodb-vs-myisam>` for the
   index table.

The value can be adjusted by following these steps:

.. rst-class:: bignums

   #. Change `ft_min_word_len` to the desired value in your MySQL configuration

      This can be done, e.g. in file :file:`my.cnf`. In the example below it will set to `3`:

      .. code-block:: text

         [mysqld]
         ft_min_word_len = 3

      A better way would be on Debian-derived systems to use a file like :file:`/etc/mysql/conf.d/fulltext.cnf`
      to separate the default values in :file:`my.cnf` with your custom settings.

      After the adjustment the MySQL server has to be restarted.

   #. Set :guilabel:`Basic` > :guilabel:`Change searchword length` in the extension configuration to the same value

      .. figure:: /Images/Configuration/extension-configuration-searchword-length.png
         :alt: Change searchword length in the extension configuration
         :class: with-border

         *Change searchword length* in the extension configuration

   #. Re-index your content

      Either by just :ref:`running the indexer <commandline-indexing>` again (full indexing is necessary)
      or by executing the following SQL command:

      .. code-block:: sql

         REPAIR TABLE tx_kesearch_index QUICK;

.. tip::
   Find more information about `fine-tuning the full-text search`_ in the MySQL documentation.


.. _fine-tuning the full-text search: https://dev.mysql.com/doc/refman/8.0/en/fulltext-fine-tuning.html