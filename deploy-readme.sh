#!/bin/bash

set -e

# Wordpress.org username
wordpressuser="wetpaintweb"
# Wordpress.org plugin slug
package="seo-editor"

echo "Pushing current README to WordPress.org"
version=`grep "Stable tag:" README.txt | awk '{print $3}'`

echo "Checking out current trunk on Wordpress SVN"
svn co https://plugins.svn.wordpress.org/${package}/trunk /tmp/readme-${package}

echo "Copying in updated README.txt"
cp "$(pwd)/README.txt" /tmp/readme-${package}/README.txt

echo "Commiting new README to Wordpress SVN trunk"
svn commit --username ${wordpressuser} -m"Updating README file" /tmp/readme-${package}

echo "Cleaning up trunk"
rm -rf /tmp/readme-${package}


echo "Checking out current ${version} version on Wordpress SVN"
svn co https://plugins.svn.wordpress.org/${package}/tags/${version} /tmp/readme-${package}

echo "Copying in updated README.txt"
cp "$(pwd)/README.txt" /tmp/readme-${package}/README.txt

echo "Commiting new README to Wordpress SVN ${version} version"
svn commit --username ${wordpressuser} -m"Updating README file" /tmp/readme-${package}

echo "Cleaning up ${version} version"
rm -rf /tmp/readme-${package}

echo "Done"
