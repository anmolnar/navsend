# navsend
NAV

## How to release

1. Create package using the web UI of module builder

2. Create SHA512 checksum
```
shasum -a 512 module\_navsend-1.0.6.zip > module\_navsend-1.0.6.zip.sha512
```

3. Create GPG signature
```
gpg --armor --output module\_navsend-1.0.6.zip.asc --detach-sig module\_navsend-1.0.6.zip
```

4. Upload all 3 files to pingus: public\_html/navsend

5. Tag the latest commit: 
```
export GPG\_TTY=`tty`
git tag -s 1.0.6
git push origin --tags
```

6. Bump version number from the UI, commit and push

