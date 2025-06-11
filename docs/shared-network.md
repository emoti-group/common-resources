# Shared Docker Network

This project provides a common Docker network that can be used by other local projects. It
is providing an easy way to connect different projects on the same Docker network, so they can talk with each other.

### Connect other projects to the shared network

Add the following to the `docker-compose.yml` file of your other projects.<br>

   ```yaml
   networks:
     common-resources-network:
       external: true
   services:
     app:
       image: your-image
       ports:
         - 8100:8080
       networks:
         - default
         - common-resources-network
   ```

**Disclaimer!**<br>
To be sure that the service connected to the `common-resources-network` still have the connection to other services <br>
defined in the same docker-compose.yml remember to also add the `default` network.
