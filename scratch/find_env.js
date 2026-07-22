const fs = require('fs');
const content = fs.readFileSync('C:/Users/Ahmed Bilal Khan/.gemini/antigravity/brain/d38a5081-7f5c-44a6-9daf-5cc62d6fbd7e/.system_generated/logs/overview.txt', 'utf8');
const lines = content.split('\n');
lines.forEach((line, idx) => {
  if (line.toLowerCase().includes('db_database') || line.toLowerCase().includes('db_username')) {
    console.log(`Line ${idx}: ${line}`);
    // Print 10 lines before and after
    for (let i = -10; i <= 10; i++) {
      if (lines[idx + i]) {
        console.log(`  [${idx+i}]: ${lines[idx+i]}`);
      }
    }
  }
});
