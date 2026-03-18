@echo off
REM Test OCR with your real ID image.
REM 1. Put your ID image (JPEG or PNG) in this folder and name it my_id.jpg (or edit IMAGE below).
REM 2. Edit FULL_NAME, BIRTHDATE, ADDRESS to match what is on the ID.
REM 3. Double-click this file or run: run_real_id_test.bat

set IMAGE=my_id.jpg
set FULL_NAME=Juan Dela Cruz
set BIRTHDATE=1990-01-15
set ADDRESS=123 Main Street Manila

cd /d "%~dp0"

if not exist "%IMAGE%" (
    echo File not found: %IMAGE%
    echo Put your ID image in this folder and name it my_id.jpg, or edit IMAGE in this batch file.
    pause
    exit /b 1
)

python test_ocr.py "%IMAGE%" "%FULL_NAME%" "%BIRTHDATE%" "%ADDRESS%"
echo.
pause
