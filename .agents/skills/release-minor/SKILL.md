---
name: release-minor
description: Automates the process of tagging a new minor release for Scribe by analyzing commit messages, updating the changelog, and creating a GitHub release.
---

# Release Minor Version

This skill automates the process of tagging a new minor release for Scribe.

## Workflow

1.  **Analyze Commits & Build Changelog**:
    -   First, update the local repo from remote (including any tags) using `git pull --all`.
    -   Identify the last release tag (e.g., using `git tag --sort=-v:refname | head -n 1`).
    -   Get all commits from the last tag to `HEAD`.
    -   Analyze commit messages to categorize them into "Added", "Modified", "Fixed", or "Removed".
    -   Draft a new section for `CHANGELOG.md` following the existing format:
        ```markdown
        ## <New Version> (<Date>)
        ### Added
        - [Description] ([#PR](link))

        ### Modified
        - ...
        ```

2.  **Update Files**:
    -   Prepend the new section to the top of the release list in `CHANGELOG.md`.
    -   Update the `public const VERSION` in `src/Scribe.php` to the new version number.

3.  **Commit and Push**:
    -   Stage `CHANGELOG.md` and `src/Scribe.php`.
    -   Commit with the message: `Bump version to <New Version>`.
    -   Push the changes to the remote repository.

4.  **Create GitHub Release**:
    -   Wait for the latest pushed commit to pass CI. Use the `gh` CLI to check its status periodically. If it does not pass, abort. DO NOT continue the rest of the flow.
    -   Use the `gh` CLI to create a new release.
    -   Command: `gh release create <New Version> --title "<New Version>" --notes "<Changelog Content>"`
    -   Ensure the notes correspond exactly to the added changelog section.
