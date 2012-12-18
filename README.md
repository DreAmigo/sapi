# SAPI API 

- Version: 0.1
- Release Date: 18th Dec 2012
- Author: Andre Bocati
- Requirements: Symphony 2.3

An interface for SAPI API.

# About Sensis API

SAPI provides a valuable service for developers seeking a business search API solution for Yellow Pages® and White Pages® business listings and advertising content.

- [About Sensis](http://developers.sensis.com.au/about)

## Installation

1. Upload the `sapi` folder to your Symphony `/extensions` folder.

2. Enable the 'SAPI API' extension on the extensions page

3. Go to System > Preferences to add the following:
    - **API KEY** -> go to http://developers.sensis.com.au/
    - **API keyword** -> "charity" for example
    - **API Categories** -> list of categories that can be generated [here](http://developers.sensis.com.au/page/category_explorer). Example: *"categoryId=40592&categoryId=40991"*

Have a read of the [Sensis API](http://developers.sensis.com.au/docs)