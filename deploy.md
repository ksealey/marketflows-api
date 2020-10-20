## Note: ONLY users in charge of deployments should be able to push layers to the image

### Deploy container with code baked in

 - Merge release/*.*.* into production
 - Commit release with tag
 - Run image deploy to copy code into container and push layer
    `sh deploy-image.sh {IMAGE-TAG}`

### Pull new image on existing instances
 - Run SSH Script to have servers pull new image
    `sh deploy-production.sh`

