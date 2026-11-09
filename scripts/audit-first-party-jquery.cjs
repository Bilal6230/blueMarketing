/**
 * Audit first-party frontend scripts for browser-native DOM / AJAX operations
 * that should use jQuery under BlueMarketing's frontend convention.
 *
 * Run: node scripts/audit-first-party-jquery.cjs
 * Optional enforcement: node scripts/audit-first-party-jquery.cjs --strict
 *
 * Excludes vendor / plugin assets and generated bundles. This is a source
 * audit, not a substitute for syntax checks or browser regression testing.
 */
'use strict';

const fs = require('node:fs');
const path = require('node:path');
const root = path.resolve(__dirname, '..');
const targets = ['resources/views', 'resources/js', 'public/js'];
const checks = [
  ['Native DOM selection/creation', /\b(?:document\.|[A-Za-z_$][\w$]*\.)?(?:getElementById|getElementsBy(?:ClassName|TagName)|querySelector(?:All)?|createElement)\s*\(/g],
  ['Native event listeners', /\b(?:document|window|[A-Za-z_$][\w$]*)\.addEventListener\s*\(/g],
  ['Native fetch / XHR', /\b(?:fetch\s*\(|new\s+XMLHttpRequest\s*\()/g],
  ['Native class handling', /\.classList\b/g],
  ['Native HTML/text updates', /\.(?:innerHTML|outerHTML|textContent|insertAdjacentHTML|appendChild)\b/g],
  ['Direct event properties', /\.(?:onclick|onchange|oninput|onsubmit)\s*=/g],
];
const ignored = /(?:^|\/)(?:vendor|node_modules|plugins|dist|build)(?:\/|$)/;
const generated = new Set(['public/js/app.js']);
const files = [];

function walk(relative) {
  const abs = path.join(root, relative);
  if (!fs.existsSync(abs)) return;
  for (const entry of fs.readdirSync(abs, {withFileTypes:true})) {
    const name = path.posix.join(relative.replaceAll('\\','/'),entry.name);
    if (ignored.test(name)) continue;
    if (entry.isDirectory()) walk(name);
    else if ((name.endsWith('.blade.php') || name.endsWith('.js')) && !generated.has(name)) files.push(name);
  }
}

for (const directory of targets) walk(directory);
files.sort();
let affected = 0, matches = 0;
for (const filename of files) {
  const source = fs.readFileSync(path.join(root,filename),'utf8');
  // Only first-party inline scripts inside Blade files count.
  const content = filename.endsWith('.blade.php')
    ? [...source.matchAll(/<script\b[^>]*>([\s\S]*?)<\/script>/gi)].map(match=>match[1]).join('\n')
    : source;
  const findings = checks.map(([label,regex])=>[label,[...content.matchAll(regex)].length])
    .filter(([,count])=>count>0);
  if (!findings.length) continue;
  affected++;
  matches += findings.reduce((sum,[,count])=>sum+count,0);
  process.stdout.write(filename+'\n');
  for (const [label,count] of findings) process.stdout.write('  '+label+': '+count+'\n');
}
process.stdout.write('\nScanned '+files.length+' first-party files; '+affected+' with matches; '+matches+' occurrences.\n');
process.stdout.write('Review matches manually; library-specific APIs and template strings can create false positives.\n');
if (process.argv.includes('--strict') && affected) process.exitCode = 1;
