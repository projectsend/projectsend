#!/bin/bash
set -e  # Exit on error

# Colors for output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Parse command line arguments
AUTO_YES=false
while getopts "y" opt; do
    case $opt in
        y)
            AUTO_YES=true
            ;;
        \?)
            echo "Invalid option: -$OPTARG" >&2
            echo "Usage: $0 [-y]"
            echo "  -y: Auto-accept prompts (auto-calculate version)"
            exit 1
            ;;
    esac
done

echo -e "${GREEN}ProjectSend Release Builder${NC}"
echo "=============================="
echo ""

# Prompt for version number (skip if -y flag is set)
if [ "$AUTO_YES" = true ]; then
    VERSION_NUMBER=""
else
    read -p "Enter version number (or press Enter to auto-calculate): " VERSION_NUMBER
fi

# Auto-calculate version if empty
if [ -z "$VERSION_NUMBER" ]; then
    echo -e "${YELLOW}Auto-calculating version from git history...${NC}"

    # Get latest release tag
    LATEST_TAG=$(git describe --tags --abbrev=0 --match="r*" 2>/dev/null)

    if [ -z "$LATEST_TAG" ]; then
        echo -e "${RED}Error: No release tags found in git history${NC}"
        echo "Please enter a version number manually"
        exit 1
    fi

    # Extract version number from tag (r1720 -> 1720)
    LATEST_VERSION=${LATEST_TAG#r}

    # Validate it's a number
    if ! [[ "$LATEST_VERSION" =~ ^[0-9]+$ ]]; then
        echo -e "${RED}Error: Latest tag '$LATEST_TAG' doesn't follow r{number} format${NC}"
        exit 1
    fi

    # Count commits since that tag
    COMMITS_SINCE=$(git rev-list ${LATEST_TAG}..HEAD --count)

    # Calculate new version
    VERSION_NUMBER=$((LATEST_VERSION + COMMITS_SINCE))

    echo -e "${GREEN}Latest release: ${LATEST_TAG} (version ${LATEST_VERSION})${NC}"
    echo -e "${GREEN}Commits since then: ${COMMITS_SINCE}${NC}"
    echo -e "${GREEN}Calculated version: r${VERSION_NUMBER}${NC}"
    echo ""
    if [ "$AUTO_YES" = false ]; then
        read -p "Use this version? (Y/n): " CONFIRM
        if [[ "$CONFIRM" =~ ^[Nn]$ ]]; then
            echo -e "${YELLOW}Cancelled by user${NC}"
            exit 0
        fi
    fi
else
    # Validate manually entered version number (only digits)
    if ! [[ "$VERSION_NUMBER" =~ ^[0-9]+$ ]]; then
        echo -e "${RED}Error: Version number must contain only digits${NC}"
        exit 1
    fi
fi

VERSION="r${VERSION_NUMBER}"
echo -e "${GREEN}Creating release: ${VERSION}${NC}"
echo ""

# Detect if we're in the ProjectSend directory
if [ -f "./bootstrap.php" ]; then
    # Running from inside ProjectSend directory
    SOURCE_DIR="$(pwd)"
    PARENT_DIR="$(dirname "$SOURCE_DIR")"
    RELEASE_DIR="$PARENT_DIR/projectsend-release"
    echo "Detected ProjectSend directory: $SOURCE_DIR"
elif [ -d "./projectsend.local" ] && [ -f "./projectsend.local/bootstrap.php" ]; then
    # Running from parent directory with projectsend.local subdirectory
    SOURCE_DIR="$(pwd)/projectsend.local"
    RELEASE_DIR="$(pwd)/projectsend-release"
    echo "Detected parent directory with projectsend.local/"
else
    echo -e "${RED}Error: Could not find ProjectSend installation${NC}"
    echo "Please run this script either from:"
    echo "  - The ProjectSend root directory (containing bootstrap.php)"
    echo "  - The parent directory containing projectsend.local/"
    exit 1
fi

# Clean up any existing release directory
if [ -d "$RELEASE_DIR" ]; then
    echo -e "${YELLOW}Cleaning up previous release...${NC}"
    rm -rf "$RELEASE_DIR"
fi

echo -e "${GREEN}Step 1: Copying source files${NC}"
echo "Source: $SOURCE_DIR"
echo "Release: $RELEASE_DIR"
cp -r "$SOURCE_DIR" "$RELEASE_DIR"
cd "$RELEASE_DIR"

echo -e "${GREEN}Step 2: Installing production dependencies${NC}"
composer install --no-dev --optimize-autoloader --no-interaction --ignore-platform-reqs

echo -e "${GREEN}Step 3: Building production assets${NC}"
npm ci --silent
npx gulp build

echo -e "${GREEN}Step 4: Updating version number${NC}"
# Update CURRENT_VERSION in the release copy
sed -i "s/define('CURRENT_VERSION', 'r[0-9]\+');/define('CURRENT_VERSION', '${VERSION}');/g" includes/app.php
# Also update the source repo and commit so git tag lands on the right commit
sed -i "s/define('CURRENT_VERSION', 'r[0-9]\+');/define('CURRENT_VERSION', '${VERSION}');/g" "$SOURCE_DIR/includes/app.php"
cd "$SOURCE_DIR"
git add includes/app.php
git commit -m "Bump version to ${VERSION}"
git push
cd "$RELEASE_DIR"
echo "Updated version to: ${VERSION}"

echo -e "${GREEN}Step 5: Cleaning cache${NC}"
rm -rf ./cache/*
mkdir -p ./cache
touch ./cache/.gitkeep

echo -e "${GREEN}Step 6: Removing configuration files${NC}"
rm -f ./includes/sys.config.php

echo -e "${GREEN}Step 7: Removing Git directories${NC}"
rm -rf .git
rm -rf .github

echo -e "${GREEN}Step 8: Removing development files${NC}"
# Remove hidden directories
rm -rf .claude .ignore .vscode .idea

# Remove development documentation (keep only README.md and SECURITY.md in root)
find . -maxdepth 1 -type f -name "*.md" ! -name "README.md" ! -name "SECURITY.md" -delete

# Remove development .md files from subdirectories
find . -type f \( \
    -name "*_PLAN.md" \
    -o -name "*_GUIDE.md" \
    -o -name "*_IMPLEMENTATION.md" \
    -o -name "ROADMAP.md" \
    -o -name "WHATS_NEW.md" \
    -o -name "CLAUDE.md" \
    -o -name "PHPSTAN.md" \
\) -delete

# Remove PHPStan files
rm -f phpstan.neon phpstan-baseline.neon phpstan-bootstrap.php

# Remove development directories
rm -rf docs_temp results reports

# Remove build scripts
rm -f make-release.sh gulpfile.js package.json package-lock.json

echo -e "${GREEN}Step 9: Removing translation source files${NC}"
# Remove .po source files (keep .mo compiled files needed at runtime, and .pot templates)
find ./lang -type f -name "*.po" -delete
find ./templates/*/lang -type f -name "*.po" -delete 2>/dev/null || true

echo -e "${GREEN}Step 10: Cleaning upload directories${NC}"

# Clean upload/temp
cd ./upload/temp
mkdir ../tmp && cp index.php ../tmp && cp .htaccess ../tmp && cp web.config ../tmp
rm -rf ./*
mv ../tmp/index.php . && mv ../tmp/.htaccess . && mv ../tmp/web.config .
rm -rf ../tmp

# Clean upload/admin
cd ../admin
mkdir ../tmp && cp index.php ../tmp
rm -rf ./*
mv ../tmp/* .
rm -rf ../tmp

# Clean upload/files
cd ../files
mkdir ../tmp && cp index.php ../tmp && cp .htaccess ../tmp && cp web.config ../tmp
rm -rf ./*
mv ../tmp/index.php . && mv ../tmp/.htaccess . && mv ../tmp/web.config .
rm -rf ../tmp

# Clean upload/thumbnails
cd ../thumbnails
mkdir ../tmp && cp index.php ../tmp
rm -rf ./*
mv ../tmp/* .
rm -rf ../tmp

# Return to release directory
cd ../../

echo -e "${GREEN}Step 11: Removing node_modules${NC}"
rm -rf node_modules

echo -e "${GREEN}Step 12: Creating ZIP archive${NC}"
ZIPFILE="projectsend-${VERSION}.zip"
zip -r -q "$ZIPFILE" *

echo -e "${GREEN}Step 13: Generating SHA256 hash${NC}"
SHA256=$(sha256sum "$ZIPFILE" | cut -d' ' -f1)

echo -e "${GREEN}Step 14: Moving release file${NC}"
OUTPUT_DIR="$(dirname "$RELEASE_DIR")"
mv "$ZIPFILE" "$OUTPUT_DIR/"
OUTPUT_FILE="$OUTPUT_DIR/$ZIPFILE"

echo -e "${GREEN}Step 15: Cleaning up temporary files${NC}"
rm -rf "$RELEASE_DIR"

echo ""
echo -e "${GREEN}✓ Release created successfully!${NC}"
echo "=============================="
echo -e "File: ${YELLOW}$OUTPUT_FILE${NC}"
echo -e "Size: ${YELLOW}$(du -h "$OUTPUT_FILE" | cut -f1)${NC}"
echo -e "SHA256: ${YELLOW}${SHA256}${NC}"
echo ""
echo -e "${GREEN}Release is ready for distribution!${NC}"
