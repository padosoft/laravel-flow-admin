#!/usr/bin/env node
import fs from 'node:fs';
import path from 'node:path';

const root = process.cwd();
const readme = fs.readFileSync(path.join(root, 'README.md'), 'utf8');
const shotsDir = path.join(root, 'resources', 'screenshoots');
const files = fs.readdirSync(shotsDir).filter((f) => f.toLowerCase().endsWith('.png'));

const missing = files.filter((file) => !readme.includes(`resources/screenshoots/${file}`));
if (missing.length > 0) {
  console.error('README is missing screenshot references:');
  for (const file of missing) console.error(`- ${file}`);
  process.exit(1);
}

console.log(`OK: ${files.length} screenshots referenced in README.`);
