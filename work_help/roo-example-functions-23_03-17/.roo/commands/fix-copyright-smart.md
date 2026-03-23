Умная вставка copyright.

Аргумент: {{args}}

Шаги:
1. Найди PHP-файл
2. Прочитай начало файла
3. Определи стратегию вставки:
   - если есть declare(strict_types=1) → after_declare
   - иначе → after_open_tag

4. Вызови:

python3 .roo/scripts/fix_copyright_smart.py "{{args}}" "<strategy>"

5. Сообщи результат
