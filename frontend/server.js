'use strict';
const path = require('path');
const { spawn } = require('child_process');

const port = process.env.PORT || 3000;
const nextBin = path.join(__dirname, 'node_modules', '.bin', 'next');

const child = spawn(process.execPath, [nextBin, 'start', '-p', String(port)], {
  cwd: __dirname,
  stdio: 'inherit',
  env: { ...process.env, PORT: String(port), NODE_ENV: 'production' },
});

child.on('exit', code => process.exit(code || 0));
