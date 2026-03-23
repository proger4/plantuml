# MCP-lite: как превратить fix_copyright.py в MCP tool

Этот пример показывает, как использовать существующий CLI-скрипт как backend для MCP-инструмента, без написания полного MCP-сервера в рамках этого задания.

## Идея

1. Оставляем детерминированную бизнес-логику в `../scripts/fix_copyright.py`.
2. Добавляем MCP tool `fix_copyright` с двумя полями:
   - `query` (string): часть имени/пути PHP-файла
   - `mode` (string, optional): `deterministic` или `smart`
3. В обработчике MCP tool вызываем локальный процесс:
   - `python3 .roo/scripts/fix_copyright.py <query>`
   - либо `python3 .roo/scripts/fix_copyright_smart.py <query> <strategy>`
4. Возвращаем stdout как результат tool-вызова.

## Почему это рабочий путь

- нет сетевых зависимостей;
- можно повторно использовать уже протестированные CLI-скрипты;
- логика не дублируется между CLI и MCP.

## Пример интерфейса инструмента

```json
{
  "name": "fix_copyright",
  "description": "Deterministic PHP copyright fixer",
  "inputSchema": {
    "type": "object",
    "properties": {
      "query": { "type": "string" },
      "mode": { "type": "string", "enum": ["deterministic", "smart"] },
      "strategy": { "type": "string", "enum": ["after_open_tag", "after_declare"] }
    },
    "required": ["query"]
  }
}
```

## Минимальный обработчик (без серверной обвязки)

```python
import subprocess


def call_fix_copyright(query: str, mode: str = "deterministic", strategy: str = "after_open_tag") -> str:
    if mode == "smart":
        cmd = ["python3", ".roo/scripts/fix_copyright_smart.py", query, strategy]
    else:
        cmd = ["python3", ".roo/scripts/fix_copyright.py", query]

    result = subprocess.run(cmd, capture_output=True, text=True, check=False)
    return (result.stdout or result.stderr).strip()
```

В реальном MCP-сервере этот обработчик просто подключается к регистрации tool.
