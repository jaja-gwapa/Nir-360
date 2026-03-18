@echo off
REM Run this to see extracted (OCR) text from an ID image in CMD.
REM Edit IMAGE path below, or pass it as first argument.

cd /d "%~dp0"

if "%~1"=="" (
  set IMAGE=c:\xampp2\htdocs\capstone_project\image.png
  echo Using default image: %IMAGE%
  echo To use another file: see_extracted_text.bat "C:\path\to\your\id.png"
  echo.
) else (
  set IMAGE=%~1
)

python extract_id_text.py "%IMAGE%"
echo.
pause
