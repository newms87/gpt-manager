#!/bin/bash

CF_DISTRIBUTION_ID=E7NP0I2WMQMYW

# Inject the commit SHA into the environment as the App Version
# Used to both verify current running version and to invalidate cache when server version does not match
VITE_APP_APP_VERSION=$(git rev-parse HEAD) yarn build-only

# Sync to S3 static website bucket and invalidate CloudFront cache for the index.html file
aws s3 sync ./dist s3://ai.on-demands.com/ --delete --profile gpt
aws cloudfront create-invalidation --profile gpt --distribution-id "$CF_DISTRIBUTION_ID" --paths /index.html
