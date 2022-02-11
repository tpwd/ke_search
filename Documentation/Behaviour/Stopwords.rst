.. include:: /Includes.rst.txt

.. _stopwords:

=========
Stopwords
=========

When using the extension with MySQL, some database servers may use the default MySQL MyISAM stopword list
(see https://dev.mysql.com/doc/refman/8.0/en/fulltext-stopwords.html). This may lead to unexpected search results
(e.g. search for german *brief* may return zero results), since *brief* is a default stopword.

It is therefore recommended to either disable the feature if possible by setting

.. code-block:: none

	ft_stopword_file = ""
