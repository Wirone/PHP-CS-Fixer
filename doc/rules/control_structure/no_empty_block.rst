=======================
Rule ``no_empty_block``
=======================

There must be no empty blocks. Blocks with comment inside are NOT considered as
empty.

Warning
-------

Using this rule is risky
~~~~~~~~~~~~~~~~~~~~~~~~

Risky if the block has side effects.

Examples
--------

Example #1
~~~~~~~~~~

.. code-block:: diff

   --- Original
   +++ New
   -<?php if ($foo) {}
   +<?php 
   \ No newline at end of file

Example #2
~~~~~~~~~~

.. code-block:: diff

   --- Original
   +++ New
   -<?php switch ($foo) {}
   +<?php 
   \ No newline at end of file

Example #3
~~~~~~~~~~

.. code-block:: diff

   --- Original
   +++ New
   -<?php while ($foo) {}
   +<?php 
   \ No newline at end of file
