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

const PRD_DIR = 'prd';
const PRD_BACKLOG_DIR = 'prd/backlog';
const PRD_COMPLETE_DIR = 'prd/complete';
const DEFAULT_ITERATIONS = 10;

const args = process.argv.slice(2);
const VERBOSE = args.includes('--verbose') || args.includes('-v');
const filteredArgs = args.filter(a => a !== '--verbose' && a !== '-v');

// Resolve PRD path - checks backlog first, then complete
function resolvePrdPath(projectName) {
  const backlogPath = path.join(PRD_BACKLOG_DIR, projectName);
  const completePath = path.join(PRD_COMPLETE_DIR, projectName);

  if (fs.existsSync(backlogPath) && fs.existsSync(path.join(backlogPath, 'project.md'))) {
    return backlogPath;
  }
  if (fs.existsSync(completePath) && fs.existsSync(path.join(completePath, 'project.md'))) {
    return completePath;
  }
  return null;
}

// Get projects from backlog sorted by creation time (newest first)
function getProjects() {
  if (!fs.existsSync(PRD_BACKLOG_DIR)) {
    return [];
  }

  return fs.readdirSync(PRD_BACKLOG_DIR)
    .filter(name => {
      const projectPath = path.join(PRD_BACKLOG_DIR, name);
      return fs.statSync(projectPath).isDirectory() &&
        fs.existsSync(path.join(projectPath, 'project.md'));
    })
    .map(name => {
      const projectPath = path.join(PRD_BACKLOG_DIR, name);
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
    const prdPath = resolvePrdPath(project);
    if (!prdPath) {
      console.error(`${colors.magenta}Project not found in ${PRD_BACKLOG_DIR}/ or ${PRD_COMPLETE_DIR}/: ${project}${colors.reset}`);
      process.exit(1);
    }
    const iterations = filteredArgs[1] ? parseInt(filteredArgs[1], 10) : DEFAULT_ITERATIONS;
    return { project, prdPath, iterations };
  }

  // Interactive mode
  const projects = getProjects();

  if (projects.length === 0) {
    console.error(`${colors.magenta}No projects found in ${PRD_BACKLOG_DIR}/${colors.reset}`);
    console.log(`${colors.dim}Create a project directory with project.md and progress.md${colors.reset}`);
    process.exit(1);
  }

  const project = await selectProject(projects);
  const prdPath = path.join(PRD_BACKLOG_DIR, project);
  const iterations = await promptIterations();

  return { project, prdPath, iterations };
}

function buildPrompt(prdPath) {
  const prdFile = `${prdPath}/project.md`;
  const progressFile = `${prdPath}/progress.md`;

  return `@${prdFile} @${progressFile}
1. Find the highest-priority task and implement it.
2. Run your tests and type checks.
3. Update the PRD with what was done.
4. Append your progress to progress.md and add checkboxes where needed in project.md.
5. Commit and push your changes.
ONLY WORK ON A SINGLE TASK.
If the PRD is complete, output <promise>COMPLETE</promise>.`;
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

    const claude = spawn('claude', [
      '--permission-mode', 'acceptEdits',
      '-p', prompt,
      '--verbose',
      '--output-format', 'stream-json'
    ], {
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
  const { project, prdPath, iterations } = await getConfig();

  // Validate project files
  const prdFile = `${prdPath}/project.md`;
  const progressFile = `${prdPath}/progress.md`;

  if (!fs.existsSync(prdFile)) {
    console.error(`${colors.magenta}Error: PRD file not found: ${prdFile}${colors.reset}`);
    process.exit(1);
  }

  if (!fs.existsSync(progressFile)) {
    console.error(`${colors.magenta}Error: Progress file not found: ${progressFile}${colors.reset}`);
    process.exit(1);
  }

  // Initialize logging
  const logFile = initLogging(prdPath);

  const prompt = buildPrompt(prdPath);

  // Clear screen and show header
  process.stdout.write('\x1b[2J\x1b[H');
  log(`${colors.bold}${colors.cyan}🔄 Ralph Loop${colors.reset}${VERBOSE ? ` ${colors.dim}(verbose)${colors.reset}` : ''}`);
  log(`${colors.dim}Project: ${project} | Max iterations: ${iterations}${colors.reset}`);
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
