.. include:: /Includes.rst.txt

.. _configuration-additional-word-characters:

==========================
Additional word characters
==========================

By default MySQL treats certain characters as word delimiters, e.g. dot (".")
and hyphen ("-"). That means words which contain one of these characters will be
treated as two words and it is not possible to search for such a word.

But in some cases it would be helpful to be able to search for such words, e.g.
serial numbers which contain a hyphen, e.g. "AB-123".

Since version 5.1.0 it is possible to make this words searchable. Go to the
extension settings and add the desired characters in "Additional word
characters". You can add multiple characters there.

.. figure:: /Images/Configuration/additional-word-characters-configuration.png
   :alt: Configure additional word characters
   :class: with-border

After that you will have to start the indexer.

Words containing the configured characters can then be searched.

.. figure:: /Images/Configuration/additional-word-characters-result.png
   :alt: Search result with additional word characters
   :class: with-border
