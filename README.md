Disclaimer: Please note that we no longer support older versions of SDKs and Modules. We recommend that the latest versions are used.

# README

# Contents

- Introduction
- Prerequisites
- Installing and configuring the module
- License

# Introduction

This OpenCart module provides an easy method to integrate with the payment gateway.
 - Supports Open versions: **3.X**

# Prerequisites

- The module requires the following prerequisites to be met in order to function correctly:
    - The 'bcmath' php extension module: https://www.php.net/manual/en/book.bc.php

> Please note that we can only offer support for the module itself. While every effort has been made to ensure the payment module is complete and bug free, we cannot guarantee normal functionality if unsupported changes are made.

# Installing and configuring the module

1. Copy the contents of the httpdocs directory into the root OpenCart directory
2. Navigate to the Extensions dropdown -> Extensions -> Payment methods -> click 'Activate'
3. Navigate to the Extensions dropdown -> Extensions -> Payment methods -> click the 'Edit' button
4. Enter your MerchantID / Secretkey and update the customer/country code
5. Select what type of integration you would like to use
6. Set what status you would like to update an order to once paid
7. Set the Enabled option to true
8. Click 'Save Changes'

License
----
MIT
