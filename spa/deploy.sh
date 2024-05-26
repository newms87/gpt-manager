#!/bin/bash

./build.sh

CF_DISTRIBUTION_ID=E7NP0I2WMQMYW

# Sync to S3 static website bucket and invalidate CloudFront cache for the index.html file
aws s3 sync ./dist s3://ai.on-demands.com/ --delete --profile gpt
aws cloudfront create-invalidation --profile gpt --distribution-id "$CF_DISTRIBUTION_ID" --paths /index.html
