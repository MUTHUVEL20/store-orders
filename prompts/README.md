# Prompt Log

The assignment brief asks for a record of the AI-assisted development: screenshots of the **actual prompts** used (from the chat or IDE panel), saved in a `/prompts` folder, so reviewers can see exactly what was asked.

This folder holds two things:

| Item | Purpose |
|------|---------|
| `PROMPTS.md` | A text log of the prompts used to build the project (summary of the initial brief, plus the follow-up prompts verbatim). |
| Screenshots (`*.png` / `*.jpg`) | Screenshots of the real prompts from the chat or IDE panel. **To be added by the author.** |

## How to add the prompt screenshots

1. Open the chat or IDE panel where the prompts were written (Claude Code, Claude chat, Copilot Chat, Cursor, ...).
2. Scroll to each prompt you sent. Capture the **prompt itself**, not only the answer, so the exact wording is visible. If a prompt is long, take several screenshots in order.
3. On Windows, press `Win + Shift + S` (Snipping Tool), select the area, then save the image with `Ctrl + S`. Save it into this `prompts` folder.
4. Name the files so they sort in the order the prompts were sent, using the entry numbers from `PROMPTS.md`:

   ```
   prompts/
   |-- 01-initial-brief-part1.png
   |-- 01-initial-brief-part2.png
   |-- 02-environment-clarification.png
   |-- 03-run-guidelines.png
   |-- 04-parallel-agents.png
   ```

5. Reference each screenshot from its entry in `PROMPTS.md` (a Markdown image link such as `![Initial brief](01-initial-brief-part1.png)`), so the log renders with the images inline on GitHub.
6. Commit the images together with the rest of the repository:

   ```powershell
   git add prompts
   git commit -m "Add prompt log screenshots"
   ```

## Tips

- Do not crop out the timestamps or the model/tool name if they are visible; they make the log more credible.
- Blur or remove anything private (API keys, personal email addresses, tokens) before committing a screenshot.
- Keep the text log and the screenshots consistent: if you add a new prompt later, add a new numbered entry to `PROMPTS.md` and a matching screenshot.
