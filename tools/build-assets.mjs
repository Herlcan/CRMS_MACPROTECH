import { readFileSync, writeFileSync, statSync } from 'node:fs';

const cssFiles = [
  'src/styles/style-improved.css'
];

const jsFiles = [
  'src/scripts/lazy-script-loader.js',
  'src/scripts/page-skeleton.js',
  'src/scripts/transition-tabs.js',
  'src/scripts/dialogs.js',
  'src/scripts/dashboard-charts.js',
  'src/scripts/notifications.js'
];

function minifiedPath(file) {
  return file.replace(/\.(css|js)$/u, '.min.$1');
}

function minifyCss(input) {
  let output = '';
  let quote = '';
  let escape = false;
  let pendingSpace = false;

  const noSpaceBefore = new Set(['{', '}', ';', ':', ',', ')', ']', '>']);
  const noSpaceAfter = new Set(['{', '}', ';', ':', ',', '(', '[', '>']);

  for (let index = 0; index < input.length; index += 1) {
    const char = input[index];
    const next = input[index + 1] || '';

    if (quote) {
      output += char;

      if (escape) {
        escape = false;
      } else if (char === '\\') {
        escape = true;
      } else if (char === quote) {
        quote = '';
      }

      continue;
    }

    if (char === '/' && next === '*') {
      index += 2;

      while (index < input.length && !(input[index] === '*' && input[index + 1] === '/')) {
        index += 1;
      }

      index += 1;
      continue;
    }

    if (char === '"' || char === "'") {
      if (pendingSpace) {
        const previous = output[output.length - 1] || '';
        if (previous && !noSpaceAfter.has(previous)) {
          output += ' ';
        }
        pendingSpace = false;
      }

      quote = char;
      output += char;
      continue;
    }

    if (/\s/u.test(char)) {
      pendingSpace = true;
      continue;
    }

    if (pendingSpace) {
      const previous = output[output.length - 1] || '';
      if (previous && !noSpaceAfter.has(previous) && !noSpaceBefore.has(char)) {
        output += ' ';
      }
      pendingSpace = false;
    }

    output += char;
  }

  return output.replace(/;\}/gu, '}').trim();
}

function minifyJs(input) {
  return input
    .replace(/\r\n?/gu, '\n')
    .split('\n')
    .map((line) => line.trim())
    .filter(Boolean)
    .join(' ');
}

function writeMinified(file, minifier) {
  const source = readFileSync(file, 'utf8');
  const output = minifier(source);
  const target = minifiedPath(file);

  writeFileSync(target, `${output}\n`);

  const sourceSize = statSync(file).size;
  const outputSize = statSync(target).size;
  const saved = sourceSize - outputSize;
  const percent = sourceSize > 0 ? ((saved / sourceSize) * 100).toFixed(1) : '0.0';

  console.log(`${target}: ${sourceSize} -> ${outputSize} bytes (${percent}% saved)`);
}

cssFiles.forEach((file) => writeMinified(file, minifyCss));
jsFiles.forEach((file) => writeMinified(file, minifyJs));
