#!/usr/bin/env node

const { spawn } = require('child_process');
const fs = require('fs');
const path = require('path');
const readline = require('readline');

// Logging setup
let logStream = null;
let jsonStream = null;

function initLogging(prdPath) {
  // Create logs directory inside the project's prd folder
  const logsDir = path.join(process.cwd(), prdPath, 'logs');
  fs.mkdirSync(logsDir, { recursive: true });

  // Create log filename with timestamp
  const timestamp = new Date().toISOString().replace(/[:.]/g, '-');
  const logFile = path.join(logsDir, `${timestamp}.log`);

  logStream = fs.createWriteStream(logFile, { flags: 'a' });

  const jsonFile = path.join(logsDir, `${timestamp}.json`);
  jsonStream = fs.createWriteStream(jsonFile, { flags: 'a' });

  // Write header
  logStream.write(`Ralph Loop Log\n`);
  logStream.write(`==============\n`);
  logStream.write(`Feature Path: ${prdPath}\n`);
  logStream.write(`Started: ${new Date().toISOString()}\n`);
  logStream.write(`\n`);

  return logFile;
}

function stripAnsi(str) {
  // Remove ANSI escape codes for clean log output
  return str.replace(/\x1b\[[0-9;]*m/g, '');
}

function log(message) {
  console.log(message);
  if (logStream) {
    logStream.write(stripAnsi(message) + '\n');
  }
}

function logRaw(message) {
  // For process.stdout.write equivalents
  process.stdout.write(message);
  if (logStream) {
    logStream.write(stripAnsi(message));
  }
}

function closeLogging() {
  if (logStream) {
    logStream.write(`\nEnded: ${new Date().toISOString()}\n`);
    logStream.end();
  }
  if (jsonStream) {
    jsonStream.end();
  }
}

// ANSI color codes
const colors = {
  reset: '\x1b[0m',
  bold: '\x1b[1m',
  dim: '\x1b[2m',
  cyan: '\x1b[36m',
  green: '\x1b[32m',
  yellow: '\x1b[33m',
  blue: '\x1b[34m',
  magenta: '\x1b[35m',
  gray: '\x1b[90m',
  white: '\x1b[37m',
};

const SPECS_BACKLOG_DIR = 'specs/backlog';
const DEFAULT_ITERATIONS = 10;

const args = process.argv.slice(2);
const VERBOSE = args.includes('--verbose') || args.includes('-v');

const filteredArgs = args.filter((a) => {
  if (a === '--verbose' || a === '-v') return false;
  return true;
});

function getConfig() {
  if (filteredArgs.length < 1) {
    console.error(`${colors.magenta}Usage: ralph-loop.js <project> [iterations]${colors.reset}`);
    console.log(`${colors.dim}This script should be invoked via ralph:loop, ralph:build, or ralph:plan.${colors.reset}`);
    process.exit(1);
  }

  const project = filteredArgs[0];
  const iterations = filteredArgs[1] ? parseInt(filteredArgs[1], 10) : DEFAULT_ITERATIONS;

  return { project, iterations };
}

function buildPrompt() {
  const prompt = process.env.RALPH_PROMPT;
  if (!prompt) {
    console.error(`${colors.magenta}Error: RALPH_PROMPT environment variable is not set.${colors.reset}`);
    console.log(`${colors.dim}This script should be invoked via ralph:loop, ralph:build, or ralph:plan.${colors.reset}`);
    process.exit(1);
  }
  return prompt;
}

function formatToolUse(toolUse) {
  const { name, input } = toolUse;
  let formatted = `${colors.yellow}⚡ ${name}${colors.reset}`;

  if (input) {
    if (input.command) {
      formatted += `\n   ${colors.dim}$ ${input.command}${colors.reset}`;
    } else if (input.file_path) {
      formatted += `\n   ${colors.dim}${input.file_path}${colors.reset}`;
    } else if (input.pattern) {
      formatted += `\n   ${colors.dim}pattern: ${input.pattern}${colors.reset}`;
    }
  }

  return formatted;
}

function formatMessage(message) {
  const { type, subtype } = message;

  switch (type) {
    case 'system':
      if (subtype === 'init') {
        return `${colors.cyan}● Session started${colors.reset} ${colors.dim}(${message.model})${colors.reset}`;
      }
      return null;

    case 'assistant':
      if (!message.message?.content) return null;

      const parts = [];
      for (const block of message.message.content) {
        if (block.type === 'text' && block.text.trim()) {
          parts.push(`${colors.white}${block.text}${colors.reset}`);
        } else if (block.type === 'tool_use') {
          parts.push(formatToolUse(block));
        }
      }
      return parts.length > 0 ? parts.join('\n') : null;

    case 'user':
      // Tool results - use the clean tool_use_result field
      const toolResult = message.tool_use_result;
      if (!toolResult) return null;

      // Handle file reads
      if (toolResult.file) {
        const { filePath, numLines } = toolResult.file;
        const fileName = filePath.split('/').pop();
        if (VERBOSE) {
          const preview = toolResult.file.content.substring(0, 300);
          return `${colors.gray}→ ${fileName} (${numLines} lines)\n${preview}${toolResult.file.content.length > 300 ? '...' : ''}${colors.reset}`;
        }
        return `${colors.gray}→ ${fileName} (${numLines} lines)${colors.reset}`;
      }

      // Handle stdout/stderr from bash
      if (toolResult.stdout !== undefined) {
        const output = toolResult.stdout || toolResult.stderr || '';
        if (!output.trim()) return `${colors.gray}→ (no output)${colors.reset}`;
        if (VERBOSE) {
          const preview = output.substring(0, 300);
          return `${colors.gray}→ ${preview}${output.length > 300 ? '...' : ''}${colors.reset}`;
        }
        const lines = output.trim().split('\n').length;
        return `${colors.gray}→ (${lines} line${lines > 1 ? 's' : ''})${colors.reset}`;
      }

      // Handle errors
      if (message.message?.content?.[0]?.is_error) {
        const errContent = message.message.content[0].content;
        return `${colors.magenta}✗ ${errContent.substring(0, 200)}${errContent.length > 200 ? '...' : ''}${colors.reset}`;
      }

      // Fallback for other types
      if (VERBOSE && toolResult.type) {
        return `${colors.gray}→ (${toolResult.type})${colors.reset}`;
      }
      return null;

    case 'result':
      const status = message.is_error ? `${colors.magenta}✗ Failed` : `${colors.green}✓ Complete`;
      const cost = message.total_cost_usd ? ` ${colors.dim}($${message.total_cost_usd.toFixed(4)})${colors.reset}` : '';
      const duration = message.duration_ms ? ` ${colors.dim}(${(message.duration_ms / 1000).toFixed(1)}s)${colors.reset}` : '';
      return `\n${status}${colors.reset}${cost}${duration}`;

    default:
      return null;
  }
}

async function runClaudeStreaming(prompt) {
  return new Promise((resolve, reject) => {
    let fullOutput = '';
    let isComplete = false;

    const claudeArgs = ['--dangerously-skip-permissions', '-p', prompt, '--verbose', '--output-format', 'stream-json'];

    const claude = spawn('claude', claudeArgs, {
      stdio: ['inherit', 'pipe', 'pipe']
    });

    const rl = readline.createInterface({
      input: claude.stdout,
      crlfDelay: Infinity
    });

    rl.on('line', (line) => {
      if (!line.trim()) return;

      // Write raw JSON line to json log
      if (jsonStream) {
        jsonStream.write(line + '\n');
      }

      try {
        const message = JSON.parse(line);

        // Format and output the message
        const formatted = formatMessage(message);
        if (formatted) {
          log(formatted);
        }

        // Extract text content from assistant messages
        if (message.type === 'assistant' && message.message?.content) {
          for (const block of message.message.content) {
            if (block.type === 'text') {
              fullOutput += block.text;

              // Check for completion marker
              if (block.text.includes('<promise>COMPLETE</promise>')) {
                isComplete = true;
              }
            }
          }
        }
      } catch (err) {
        // Non-JSON line, just output it
        log(line);
      }
    });

    claude.stderr.on('data', (data) => {
      process.stderr.write(data);
      if (logStream) {
        logStream.write(data.toString());
      }
    });

    claude.on('close', (code) => {
      if (code !== 0) {
        reject(new Error(`Claude exited with code ${code}`));
      } else {
        resolve({ output: fullOutput, isComplete });
      }
    });

    claude.on('error', (err) => {
      reject(err);
    });
  });
}

async function main() {
  const { project, iterations } = getConfig();
  const prompt = buildPrompt();

  // Initialize logging in project's spec directory if it exists
  const specPath = path.join(SPECS_BACKLOG_DIR, project);
  const logFile = fs.existsSync(specPath) ? initLogging(specPath) : null;

  // Clear screen and show header
  process.stdout.write('\x1b[2J\x1b[H');
  log(`${colors.bold}${colors.cyan}🔄 Ralph Loop${colors.reset}${VERBOSE ? ` ${colors.dim}(verbose)${colors.reset}` : ''}`);
  log(`${colors.dim}Project: ${project} | Max iterations: ${iterations}${colors.reset}`);
  if (logFile) {
    log(`${colors.dim}Log file: ${logFile}${colors.reset}`);
  }
  log(`${colors.dim}${'─'.repeat(50)}${colors.reset}`);

  for (let i = 1; i <= iterations; i++) {
    log(`\n${colors.bold}${colors.blue}━━━ Iteration ${i}/${iterations} ━━━${colors.reset}\n`);

    try {
      const { output, isComplete } = await runClaudeStreaming(prompt);

      if (isComplete) {
        log(`\n${colors.green}${colors.bold}🎉 Loop complete after ${i} iteration${i > 1 ? 's' : ''}.${colors.reset}`);
        closeLogging();
        process.exit(0);
      }
    } catch (err) {
      log(`${colors.magenta}Error in iteration ${i}: ${err.message}${colors.reset}`);
      closeLogging();
      process.exit(1);
    }
  }

  log(`\n${colors.yellow}⚠ Reached ${iterations} iterations without full completion.${colors.reset}`);
  closeLogging();
}

main();
