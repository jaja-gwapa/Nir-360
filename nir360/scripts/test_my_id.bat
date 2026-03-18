@echo off
REM Test OCR with your image: capstone_project\image.png
REM Edit the 3 values below to match what is written ON YOUR ID (name, birthdate, address).

cd /d "%~dp0"

set IMAGE=c:\xampp2\htdocs\capstone_project\image.png
set FULL_NAME=Julio Martinez
set BIRTHDATE=1990-01-15
set ADDRESS=Real Address St Bucroz Occidental

python test_ocr.py "%IMAGE%" "%FULL_NAME%" "%BIRTHDATE%" "%ADDRESS%"
echo.
pause
