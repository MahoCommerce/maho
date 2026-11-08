"""List the pages of mahocommerce.com that a pull request adds, changes or removes.

SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
SPDX-License-Identifier: OSL-3.0

Usage: changed_pages.py <maho checkout> <hook file> <built site> <pull request> <repository>

Standard input is the output of `git diff --name-status --no-renames`. Standard output is the
JSON that preview.js and docs-preview.yml read. The docs hook of the site maps a file to its page.
"""

import importlib.util
import json
import os
import sys


def load_hook(path):
    spec = importlib.util.spec_from_file_location('maho_docs', path)
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    return module


def page_url(uri):
    folder, name = uri.rsplit('/', 1)
    stem = name[: -len('.md')]
    return f'{folder}/' if stem in ('index', 'README') else f'{folder}/{stem}/'


def main():
    maho, hook_file, site, pull_request, repository = sys.argv[1:6]
    hook = load_hook(hook_file)
    maho = os.path.abspath(maho)
    folders = [(name, os.path.join(maho, 'docs', name)) for name in hook.ROOT_FOLDERS]
    folders += list(hook.module_folders(maho))

    changed, removed = set(), set()
    for line in sys.stdin:
        status, path = line.rstrip('\n').split('\t', 1)
        if not path.endswith('.md'):
            continue
        absolute = os.path.join(maho, path)
        for site_folder, folder in folders:
            if absolute.startswith(folder + os.sep):
                url = page_url(f'{site_folder}/{os.path.relpath(absolute, folder)}'.replace(os.sep, '/'))
                break
        else:
            continue
        if status == 'D':
            removed.add(url)
        elif os.path.isfile(os.path.join(site, url, 'index.html')):
            changed.add(url)

    json.dump({
        'pr': int(pull_request),
        'repository': repository,
        'changed': sorted(changed),
        'removed': sorted(removed),
    }, sys.stdout)


main()
