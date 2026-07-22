const fs = require('fs');
const content = fs.readFileSync('C:/Users/Ahmed Bilal Khan/.gemini/antigravity/brain/d38a5081-7f5c-44a6-9daf-5cc62d6fbd7e/.system_generated/logs/overview.txt', 'utf8');
const lines = content.split('\n');
lines.forEach((line, idx) => {
  if (line.includes('"step_index":935')) {
    for (let i = 0; i < 15; i++) {
      console.log(lines[idx + i]);
    }
  }
});
