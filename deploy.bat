@echo off
cls

REM This is a sample batch file to get things up and running quickly under windows

REM It reduces the learning curve and takes a person from having no working folder
REM to a fully installed MODx build environment with Gitify support

REM Place this batch file in location that makes sense to your build environment
REM setup. I placed mine in my Ampps install folder under a sub-folder I created
REM called scripts.

REM You need to ensure the Windows PATH environment variable has an entry pointing
REM to the location of the folder your deploy.bat folder is stored if you want to
REM be able to use the batch file quickly and easily no matter where your command
REM console opens.

REM Once the script has completed it's execution, it will dump you into the project
REM folder so you can continue to work on the newly created MODx build environment.

REM Change the variable below in order to align with your configuration

REM The variable gitifyModxRoot should be changed to the destination base folder that
REM you want to use for multiple MODx/Gitify projects

REM change the variable below (your_projects_root_folder) to the path you want to use
REM as a base folder for all of your project deployments

set gitifyModxRoot=your_projects_root_folder

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
