# Plugin Update Issue - Analysis and Fix

## Problem
WordPress was deactivating the Content Freeze Out plugin with the error: **"Plugin file does not exist"** after running an update. This occurred despite the plugin file being present in the repository.

## Root Cause
The issue was in the `updater.php` file's `select_source()` method, which handles renaming extracted update packages to the correct folder structure.

When WordPress downloads and extracts a plugin update:
1. It downloads a zip file (created by GitHub Actions with `git archive --prefix=content-freeze-out/`)
2. It extracts to a temporary directory (e.g., `/tmp/extract_12345/`)
3. The extracted directory might contain a nested `content-freeze-out/` folder
4. The `select_source()` hook intercepts this and must rename it to match the plugin's directory name

**The problem:** The original `select_source()` method didn't handle the case where the plugin file was nested inside the extracted folder. It only checked if the file existed at the root level of the extract. When it didn't find it at the root, it returned an error instead of checking for nested directories.

## Solution
Updated the `select_source()` method in `updater.php` to:

1. **Check for plugin at root level** - If `content-freeze-out.php` exists at the root of the extracted folder:
   - If the folder is already named `content-freeze-out/`, return it as-is
   - Otherwise, rename it to `content-freeze-out/`

2. **Check for nested plugin** - If the file isn't at root, scan subdirectories for it:
   - Iterate through directories in the extracted package
   - Check if any subdirectory contains the plugin file
   - Move the nested folder to the correct location

3. **Validate package structure** - Return helpful error messages if:
   - The package doesn't contain the plugin file at all
   - Source and remote paths are the same (attempting to move a folder onto itself)
   - The move operation fails due to filesystem permissions

## Changes Made

### `/updater.php`
- Enhanced `select_source()` method with improved folder structure handling
- Added check for nested directories using `$wp_filesystem->dirlist()`
- Added safeguard to prevent moving source onto itself
- Improved error messages

### `/tests/updater-test.php`
- Added `dirlist()` method to the test filesystem mock
- Improved directory detection logic in the mock

## Testing
All existing unit tests pass, including:
- ✅ Release archive keeps its existing directory
- ✅ GitHub archive is renamed before installation
- ✅ WordPress installs to the original plugin path
- ✅ Custom plugin folders are preserved
- ✅ Other plugins are untouched
- ✅ Previous errors are preserved
- ✅ Missing plugin file fails before installation
- ✅ Failed rename fails the update
- ✅ Flat archive with wrong folder name fails safely
- ✅ Release ZIP is selected for updates
- ✅ GitHub archive is used when no ZIP asset exists

## How to Recover
If the plugin is already deactivated:

1. **Reactivate manually:**
   - Go to WordPress Admin > Plugins
   - Find "Content Freeze Out" 
   - Click "Activate"

2. **Try the update again:**
   - Go to WordPress Admin > Plugins
   - Ensure "Content Freeze Out" is active
   - Go to Plugins > Updates (or Dashboard > Updates)
   - Click "Update Now" for Content Freeze Out
   - The plugin should now extract and install correctly

3. **Verify:**
   - Check that the plugin remains active after update
   - Go to Plugins page and confirm "Content Freeze Out" is listed as active

## Prevention
The GitHub Actions release workflow (`/.github/workflows/release.yml`) creates the zip file with `--prefix=content-freeze-out/`, which is correct. With this fix, WordPress will properly handle the nested folder structure during extraction and installation.
