git config core.hooksPath `git rev-parse --show-toplevel`/.githooks
echo "Git pre-commit hook is installed. This hook fixes the src/Constants.php version for each commits."