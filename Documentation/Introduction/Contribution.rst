..  include:: /Includes.rst.txt

..  _introduction-contribution:

============
Contribution
============

Contributions are welcome. The full local setup, the test workflow and the
conventions CI enforces live in `CONTRIBUTING.md
<https://github.com/schliesser/imaginator/blob/main/CONTRIBUTING.md>`__. By
contributing you agree your work is licensed under the project's
**GPL-2.0-or-later**.

Quick start
===========

A committed `DDEV <https://ddev.com/>`__ config provides a clickable sandbox.
After a fresh clone:

..  code-block:: bash
    :caption: Boot the dev environment

    ddev start             # web (nginx-fpm, PHP 8.3), MariaDB, imgproxy
    ddev setup             # composer install into .Build/
    ddev demo              # clickable demo at https://imaginator.ddev.site/

Backend login on every instance: **admin** / **Password.1**.

Tests & coding standards
========================

Run everything through DDEV before opening a pull request if possible:

..  code-block:: bash
    :caption: Test & lint

    ddev test all          # PHPUnit unit + functional
    ddev lint              # PHPStan (level 8) + php-cs-fixer (dry-run)
    ddev cgl-fix           # auto-fix coding-style violations

Development follows **Test Driven Development**: write a failing test, verify it
fails, add the implementation and verify it passes. Use `Conventional Commits
<https://www.conventionalcommits.org/>`__ for messages (``feat:``, ``fix:``,
``docs:``, …), branch off ``main`` and open the pull request against ``main``.
