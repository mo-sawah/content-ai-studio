const fs = require('fs');
const path = require('path');

// Get the new version from package.json
const packageJson = require('./package.json');
const newVersion = packageJson.version;

if (!newVersion) {
    console.error('Error: Version not found in package.json');
    process.exit(1);
}

console.log(`Syncing to version ${newVersion}...`);

// --- List of files to update ---
const filesToUpdate = [
    {
        path: path.join(__dirname, 'article-to-media.php'),
        patterns: [
            /(^\s*\* Version:\s*)[0-9a-zA-Z.-]+$/m,
            // --- THIS IS THE CORRECTED LINE ---
            /(define\('ATM_VERSION',\s*')[0-9a-zA-Z.-]+('\);)/m 
        ],
        replacements: [
            `$1${newVersion}`,
            `$1${newVersion}$2`
        ]
    }
];

filesToUpdate.forEach(fileInfo => {
    try {
        let content = fs.readFileSync(fileInfo.path, 'utf8');
        let updated = false;

        fileInfo.patterns.forEach((pattern, index) => {
            if (pattern.test(content)) {
                content = content.replace(pattern, fileInfo.replacements[index]);
                updated = true;
            }
        });

        if (updated) {
            fs.writeFileSync(fileInfo.path, content, 'utf8');
            console.log(`✅ Updated version in ${fileInfo.path}`);
        } else {
            console.warn(`⚠️  Pattern not found in ${fileInfo.path}. File not updated.`);
        }
    } catch (err) {
        console.error(`❌ Error processing file ${fileInfo.path}:`, err);
        process.exit(1);
    }
});

console.log('Version sync complete.');