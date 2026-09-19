import fs from 'fs';

const raw = JSON.parse(fs.readFileSync('docs/audit_artifacts/s01_soak_10x_raw.json', 'utf8'));

console.log("=== 10x Soak Summary Analysis ===");
console.log("Total runs:", raw.length);

const timings = raw.map(r => r.loginTimeMs);
const minTime = Math.min(...timings);
const maxTime = Math.max(...timings);
const avgTime = (timings.reduce((a, b) => a + b, 0) / timings.length).toFixed(1);

console.log(`Login & S01 Nav Load Time (ms): Min=${minTime}, Max=${maxTime}, Avg=${avgTime}`);

const consoleErrorsCount = raw.map(r => r.consoleErrors.length);
console.log("Console Errors per run:", consoleErrorsCount);

const networkFailuresCount = raw.map(r => r.networkFailures.length);
console.log("Network Failures per run:", networkFailuresCount);

console.log("\nComputed Tokens (Run 1):", JSON.stringify(raw[0].computedTokens, null, 2));
