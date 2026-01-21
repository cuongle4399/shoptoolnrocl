@echo off
chcp 65001 > nul
setlocal enabledelayedexpansion

echo ===============================
echo PUSH FULL CODE LEN GITHUB
echo (DEPLOY RENDER)
echo ===============================

:: Fix loi index.lock
if exist ".git\index.lock" (
    echo Phat hien index.lock -> dang xoa...
    del /f /q ".git\index.lock"
)

:: Khoi tao git neu chua co
if not exist ".git" (
    echo Khoi tao git repository...
    git init
    git branch -M main
    git remote add origin https://github.com/cuongle4399/shoptoolnrocl.git
)

:: Tao .gitignore NEU CHUA CO
if not exist ".gitignore" (
    echo Tao .gitignore...
    (
        echo .env
        echo .env.*
        echo vendor/
        echo node_modules/
        echo .DS_Store
    ) > .gitignore
)

:: Bao dam .env khong bao gio bi push
git rm --cached .env >nul 2>&1

echo.
echo Trang thai truoc khi add:
git status

:: ADD TAT CA (KE CA .gitignore + .bat)
echo.
echo Dang add tat ca file...
git add -A

echo.
echo Trang thai sau khi add:
git status

:: Nhap commit message
set /p msg=Nhap commit message: 
if "!msg!"=="" set msg=init php shop deploy render

:: Commit
git commit -m "!msg!" || echo Khong co thay doi moi

:: Push len GitHub
echo.
echo Dang push len GitHub...
git push -u origin main

echo.
echo ===============================
echo PUSH THANH CONG - SAN SANG DEPLOY
echo ===============================
pause
