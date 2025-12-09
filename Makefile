check: 
	find . -type f -name "*.php" -print0 | xargs -0 -n1 php -l