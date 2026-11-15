"""List the pages of mahocommerce.com that a pull request adds, changes or removes, with the diff of each.

SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
SPDX-License-Identifier: OSL-3.0

Usage: changed_pages.py <maho checkout> <hook file> <built site> <pull request> <repository>

The script compares HEAD^1 and HEAD of the maho checkout. It writes _preview/changed.json, which
preview.js and docs-preview.yml read, and _preview/diff.json, which preview.js reads when a person
opens the changes of a page. The docs hook of the site maps a file to its page.
"""

import importlib.util
import json
import os
import re
import subprocess
import sys

BASE, HEAD = 'HEAD^1', 'HEAD'


def load_hook(path):
    spec = importlib.util.spec_from_file_location('maho_docs', path)
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    return module


def page_url(uri):
    folder, name = uri.rsplit('/', 1)
    stem = name[: -len('.md')]
    return f'{folder}/' if stem in ('index', 'README') else f'{folder}/{stem}/'


def git(maho, *args):
    return subprocess.run(['git', '-C', maho, *args], check=True, capture_output=True, text=True).stdout


def word_diff(maho, path):
    """Return the hunks of the word diff of a file: each hunk has its first line in the new file,
    and a list of lines, each a list of [operation, text] parts, where operation is ' ', '-' or '+'."""
    hunks, line = [], []
    for row in git(maho, 'diff', '--word-diff=porcelain', '--no-color', BASE, HEAD, '--', path).splitlines():
        header = re.match(r'@@ -\d+(?:,\d+)? \+(\d+)', row)
        if header:
            hunks.append({'line': int(header.group(1)), 'lines': []})
            line = []
        elif not hunks:
            continue
        elif row == '~':
            hunks[-1]['lines'].append(line)
            line = []
        elif row[:1] in (' ', '-', '+'):
            line.append([row[0], row[1:]])
    return hunks


def main():
    maho, hook_file, site, pull_request, repository = sys.argv[1:6]
    hook = load_hook(hook_file)
    maho = os.path.abspath(maho)
    folders = [(name, os.path.join(maho, 'docs', name)) for name in hook.ROOT_FOLDERS]
    folders += list(hook.module_folders(maho))

    changed, added, removed, diffs = set(), set(), set(), {}
    for row in git(maho, 'diff', '--name-status', '--no-renames', BASE, HEAD).splitlines():
        status, path = row.split('\t', 1)
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
            if status == 'A':
                added.add(url)
            else:
                diffs[url] = word_diff(maho, path)

    os.makedirs(os.path.join(site, '_preview'), exist_ok=True)
    with open(os.path.join(site, '_preview', 'changed.json'), 'w') as file:
        json.dump({
            'pr': int(pull_request),
            'repository': repository,
            'changed': sorted(changed),
            'added': sorted(added),
            'removed': sorted(removed),
        }, file)
    with open(os.path.join(site, '_preview', 'diff.json'), 'w') as file:
        json.dump(diffs, file)


main()
