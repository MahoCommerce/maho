// SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
// SPDX-License-Identifier: OSL-3.0
//
// Count the grades of benchmark runs and print Markdown tables.
//
// Usage: node <skill>/summary.mjs <label>=<run folder>[,<run folder>...] ...
//   node summary.mjs "Haiku before=before/haiku/r1,before/haiku/r2" "Haiku after=after/haiku/r1,after/haiku/r2"
//
// Each run folder holds a grades.json: a list of
//   { "case": "price-option", "message": 1, "grade": "correct|incomplete|honest|wrong|passed|failed", "docs": true, "note": "..." }

import { existsSync, readFileSync } from 'node:fs';
import { join } from 'node:path';

const GRADES = ['correct', 'incomplete', 'honest', 'wrong'];
const groups = process.argv.slice(2).map((argument) => {
    const [label, folders] = argument.split('=');
    const rows = folders.split(',').flatMap((folder) => {
        const file = join(folder, 'grades.json');
        return existsSync(file) ? JSON.parse(readFileSync(file, 'utf8')) : [];
    });
    return { label, runs: folders.split(',').length, rows };
});

const count = (rows, grade) => rows.filter((row) => row.grade === grade).length;
const percent = (part, all) => (all === 0 ? '-' : `${Math.round((part / all) * 100)}%`);

console.log('| Model and code | Runs | Answers | ' + GRADES.map((g) => g[0].toUpperCase() + g.slice(1)).join(' | ') + ' | Correct or honest | Docs read | Writes passed |');
console.log('|---|---|---|' + GRADES.map(() => '---|').join('') + '---|---|---|');
for (const { label, runs, rows } of groups) {
    const answers = rows.filter((row) => GRADES.includes(row.grade));
    const writes = rows.filter((row) => ['passed', 'failed'].includes(row.grade));
    const docs = answers.filter((row) => row.docs === true).length;
    console.log(`| ${label} | ${runs} | ${answers.length} | ${GRADES.map((g) => count(answers, g)).join(' | ')} | ${percent(count(answers, 'correct') + count(answers, 'honest'), answers.length)} | ${percent(docs, answers.length)} | ${count(writes, 'passed')} of ${writes.length} |`);
}

const cases = [...new Set(groups.flatMap(({ rows }) => rows.map((row) => row.case)))];
console.log('\n| Case | ' + groups.map(({ label }) => label).join(' | ') + ' |');
console.log('|---|' + groups.map(() => '---|').join(''));
for (const name of cases) {
    const cells = groups.map(({ rows }) => {
        const own = rows.filter((row) => row.case === name);
        if (own.length === 0) {
            return '';
        }
        const good = own.filter((row) => ['correct', 'honest', 'passed'].includes(row.grade)).length;
        return `${good} of ${own.length}`;
    });
    console.log(`| ${name} | ${cells.join(' | ')} |`);
}
