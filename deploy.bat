@echo off
cls

REM This is a sample batch file to get things up and running quickly under windows
REM
REM It reduces the learning curve and takes a person from having no working folder
REM to a fully installed MODx build environment with Gitify support
REM
REM I placed this batch file in my Ampps primary folder under a sub-folder I created
REM called scripts. I then updated my Windows PATH environment variable and added the
REM scripts folder in order to be able to run the script anywhere.
REM
REM Once the script has completed it's execution, it dump you into the development
REM folder so you can continue to work on the newly created MODx build environment.
REM
REM Change the variable below in order to align with your configuration
REM
REM The root foler to create MODx install sub-folders in
set gitifyModxRoot=k:\ampps\www

echo.
echo  Preparing new modx install with Gitify and composer support
echo.
echo  If you have forgotten to create a database user and password for this MODx install do so now before proceeding
echo.
pause
echo.
echo Cloning down Gitify repository
echo.
git clone https://github.com/modmore/Gitify.git %gitifyModxRoot%\%1
k:
cd %gitifyModxRoot%\%1
echo.
echo Installing Composer
echo.
cmd /ccomposer install
echo.
echo Installing Gitify
echo.
cmd /cGitify install:modx
echo.
echo Initializing Gitify configuration and installing cloning MODx
echo.
cmd /cGitify init
echo.
echo. Process complete.
