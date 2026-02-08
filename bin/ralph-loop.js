#!/usr/bin/env node

const { spawn } = require('child_process');
const fs = require('fs');
const path = require('path');
const readline = require('readline');

// Logging setup
let logStream = null;

function initLogging(prdPath) {
  // Create logs directory inside the project's prd folder
  const logsDir = path.join(process.cwd(), prdPath, 'logs');
  fs.mkdirSync(logsDir, { recursive: true });

  // Create log filename with timestamp
  const timestamp = new Date().toISOString().replace(/[:.]/g, '-');
  const logFile = path.join(logsDir, `${timestamp}.log`);

  logStream = fs.createWriteStream(logFile, { flags: 'a' });

  // Write header
  logStream.write(`Ralph Loop Log\n`);
  logStream.write(`==============\n`);
  logStream.write(`PRD Path: ${prdPath}\n`);
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

const SPECS_DIR = 'specs';
const SPECS_BACKLOG_DIR = 'specs/backlog';
const SPECS_COMPLETE_DIR = 'specs/complete';
const DEFAULT_ITERATIONS = 10;

const args = process.argv.slice(2);
const VERBOSE = args.includes('--verbose') || args.includes('-v');

// Parse --settings flag
let CLAUDE_SETTINGS = null;
const settingsIndex = args.indexOf('--settings');
if (settingsIndex !== -1 && args[settingsIndex + 1]) {
  CLAUDE_SETTINGS = args[settingsIndex + 1];
}

const filteredArgs = args.filter((a, i) => {
  if (a === '--verbose' || a === '-v') return false;
  if (a === '--settings') return false;
  if (i > 0 && args[i - 1] === '--settings') return false;
  return true;
});

// Resolve spec path - checks backlog first, then complete
// Supports both exact match and partial match (feature name without date prefix)
function resolveSpecPath(projectName) {
  const backlogPath = path.join(SPECS_BACKLOG_DIR, projectName);
  const completePath = path.join(SPECS_COMPLETE_DIR, projectName);

  // Try exact match first
  if (fs.existsSync(backlogPath) && fs.existsSync(path.join(backlogPath, 'PRD.md'))) {
    return backlogPath;
  }
  if (fs.existsSync(completePath) && fs.existsSync(path.join(completePath, 'PRD.md'))) {
    return completePath;
  }

  // Try partial match (search for feature name after date prefix)
  const datePattern = /^\d{4}-\d{2}-\d{2}-(.+)$/;

  if (fs.existsSync(SPECS_BACKLOG_DIR)) {
    const dirs = fs.readdirSync(SPECS_BACKLOG_DIR);
    for (const dir of dirs) {
      const match = dir.match(datePattern);
      if (match && (match[1] === projectName || dir.includes(projectName))) {
        const fullPath = path.join(SPECS_BACKLOG_DIR, dir);
        if (fs.existsSync(path.join(fullPath, 'PRD.md'))) {
          return fullPath;
        }
      }
    }
  }

  if (fs.existsSync(SPECS_COMPLETE_DIR)) {
    const dirs = fs.readdirSync(SPECS_COMPLETE_DIR);
    for (const dir of dirs) {
      const match = dir.match(datePattern);
      if (match && (match[1] === projectName || dir.includes(projectName))) {
        const fullPath = path.join(SPECS_COMPLETE_DIR, dir);
        if (fs.existsSync(path.join(fullPath, 'PRD.md'))) {
          return fullPath;
        }
      }
    }
  }

  return null;
}

// Get specs from backlog sorted by creation time (newest first)
function getProjects() {
  if (!fs.existsSync(SPECS_BACKLOG_DIR)) {
    return [];
  }

  return fs.readdirSync(SPECS_BACKLOG_DIR)
    .filter(name => {
      const projectPath = path.join(SPECS_BACKLOG_DIR, name);
      return fs.statSync(projectPath).isDirectory() &&
        fs.existsSync(path.join(projectPath, 'PRD.md'));
    })
    .map(name => {
      const projectPath = path.join(SPECS_BACKLOG_DIR, name);
      const stat = fs.statSync(projectPath);
      return { name, birthtime: stat.birthtime };
    })
    .sort((a, b) => b.birthtime - a.birthtime)
    .map(p => p.name);
}

// Interactive project selector
async function selectProject(projects) {
  return new Promise((resolve) => {
    let selectedIndex = 0;

    const render = () => {
      // Clear previous render
      process.stdout.write('\x1b[2J\x1b[H');
      console.log(`${colors.bold}${colors.cyan}🔄 Ralph Loop${colors.reset}\n`);
      console.log(`${colors.dim}Select a project (↑/↓ to navigate, Enter to select, q to quit):${colors.reset}\n`);

      projects.forEach((project, index) => {
        const prefix = index === selectedIndex ? `${colors.cyan}❯ ` : '  ';
        const style = index === selectedIndex ? colors.bold : colors.dim;
        console.log(`${prefix}${style}${project}${colors.reset}`);
      });
    };

    render();

    readline.emitKeypressEvents(process.stdin);
    if (process.stdin.isTTY) {
      process.stdin.setRawMode(true);
    }

    const onKeypress = (str, key) => {
      if (key.name === 'up' && selectedIndex > 0) {
        selectedIndex--;
        render();
      } else if (key.name === 'down' && selectedIndex < projects.length - 1) {
        selectedIndex++;
        render();
      } else if (key.name === 'return') {
        cleanup();
        resolve(projects[selectedIndex]);
      } else if (key.name === 'q' || (key.ctrl && key.name === 'c')) {
        cleanup();
        process.exit(0);
      }
    };

    const cleanup = () => {
      process.stdin.removeListener('keypress', onKeypress);
      if (process.stdin.isTTY) {
        process.stdin.setRawMode(false);
      }
      console.log();
    };

    process.stdin.on('keypress', onKeypress);
  });
}

// Prompt for iterations
async function promptIterations() {
  const rl = readline.createInterface({
    input: process.stdin,
    output: process.stdout
  });

  return new Promise((resolve) => {
    rl.question(`${colors.dim}Iterations (default ${DEFAULT_ITERATIONS}): ${colors.reset}`, (answer) => {
      rl.close();
      const num = parseInt(answer, 10);
      resolve(isNaN(num) || num <= 0 ? DEFAULT_ITERATIONS : num);
    });
  });
}

// Parse arguments or run interactive mode
async function getConfig() {
  if (filteredArgs.length >= 1) {
    // Project provided as argument
    const project = filteredArgs[0];
    const specPath = resolveSpecPath(project);
    if (!specPath) {
      console.error(`${colors.magenta}Spec not found in ${SPECS_BACKLOG_DIR}/ or ${SPECS_COMPLETE_DIR}/: ${project}${colors.reset}`);
      process.exit(1);
    }
    const iterations = filteredArgs[1] ? parseInt(filteredArgs[1], 10) : DEFAULT_ITERATIONS;
    return { project: path.basename(specPath), specPath, iterations };
  }

  // Interactive mode
  const projects = getProjects();

  if (projects.length === 0) {
    console.error(`${colors.magenta}No specs found in ${SPECS_BACKLOG_DIR}/${colors.reset}`);
    console.log(`${colors.dim}Run '/prd' to create a new spec first${colors.reset}`);
    process.exit(1);
  }

  const project = await selectProject(projects);
  const specPath = path.join(SPECS_BACKLOG_DIR, project);
  const iterations = await promptIterations();

  return { project, specPath, iterations };
}

function buildPrompt(specPath) {
  const prdFile = `${specPath}/PRD.md`;
  const planFile = `${specPath}/IMPLEMENTATION_PLAN.md`;

  return `@${prdFile} @${planFile}
1. Find the highest-priority unchecked task in IMPLEMENTATION_PLAN.md and implement it.
2. Run your tests and type checks.
3. Mark the task as complete in IMPLEMENTATION_PLAN.md by checking its checkbox.
4. Commit and push your changes.
ONLY WORK ON A SINGLE TASK.
If all tasks in IMPLEMENTATION_PLAN.md are complete, output <promise>COMPLETE</promise>.`;
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

    const claudeArgs = CLAUDE_SETTINGS
      ? ['--settings', CLAUDE_SETTINGS, '-p', prompt, '--verbose', '--output-format', 'stream-json']
      : ['--permission-mode', 'acceptEdits', '-p', prompt, '--verbose', '--output-format', 'stream-json'];

    const claude = spawn('claude', claudeArgs, {
      stdio: ['inherit', 'pipe', 'pipe']
    });

    const rl = readline.createInterface({
      input: claude.stdout,
      crlfDelay: Infinity
    });

    rl.on('line', (line) => {
      if (!line.trim()) return;

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
  const { project, specPath, iterations } = await getConfig();

  // Validate spec files
  const prdFile = `${specPath}/PRD.md`;
  const planFile = `${specPath}/IMPLEMENTATION_PLAN.md`;

  if (!fs.existsSync(prdFile)) {
    console.error(`${colors.magenta}Error: PRD.md not found: ${prdFile}${colors.reset}`);
    process.exit(1);
  }

  if (!fs.existsSync(planFile)) {
    console.error(`${colors.magenta}Error: IMPLEMENTATION_PLAN.md not found: ${planFile}${colors.reset}`);
    console.log(`${colors.dim}Run 'php artisan ralph:plan ${project}' first to create an implementation plan.${colors.reset}`);
    process.exit(1);
  }

  // Initialize logging
  const logFile = initLogging(specPath);

  const prompt = buildPrompt(specPath);

  // Clear screen and show header
  process.stdout.write('\x1b[2J\x1b[H');
  log(`${colors.bold}${colors.cyan}🔄 Ralph Loop${colors.reset}${VERBOSE ? ` ${colors.dim}(verbose)${colors.reset}` : ''}`);
  log(`${colors.dim}Spec: ${project} | Max iterations: ${iterations}${colors.reset}`);
  log(`${colors.dim}Log file: ${logFile}${colors.reset}`);
  log(`${colors.dim}${'─'.repeat(50)}${colors.reset}`);

  for (let i = 1; i <= iterations; i++) {
    log(`\n${colors.bold}${colors.blue}━━━ Iteration ${i}/${iterations} ━━━${colors.reset}\n`);

    try {
      const { output, isComplete } = await runClaudeStreaming(prompt);

      if (isComplete) {
        log(`\n${colors.green}${colors.bold}🎉 PRD complete after ${i} iteration${i > 1 ? 's' : ''}.${colors.reset}`);
        closeLogging();
        process.exit(0);
      }
    } catch (err) {
      log(`${colors.magenta}Error in iteration ${i}: ${err.message}${colors.reset}`);
      closeLogging();
      process.exit(1);
    }
  }

  log(`\n${colors.yellow}⚠ Completed ${iterations} iterations without PRD completion.${colors.reset}`);
  closeLogging();
}

main();
