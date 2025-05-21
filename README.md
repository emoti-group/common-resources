# Shared Services and Network with Docker Compose

This project provides a shared set of services and a common Docker network that can be used by other local projects. It is designed to streamline development by centralizing commonly used services and providing an easy way to connect different projects on the same Docker network.

## Features

- **Common Docker Network**: A shared network (`common-resources-network`) that allows local projects to communicate with each other.
- **Shared Services**: Preconfigured services that can be reused across projects.

## Getting Started

### Prerequisites

- [Docker](https://www.docker.com/get-started) installed on your system.
- [Docker Compose](https://docs.docker.com/compose/) installed.

### Usage

1. #### Clone this repository
   ```bash
   git clone git@ssh-gitlab.emoti.dev:emoti/gifts/common-resources.git
   cd common-resources
   cp .env.example .env
   ```
2. #### Start the shared services:
   ```bash
   docker-compose up -d
   ``` 
     
3. #### Connect other projects to the shared network
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
   _**Disclaimers**_ <br>
   To be sure that other services defined in the docker-compose.yml are accessible for container connected to `common-resources-network`
      remember to add `default` network to the services that you want to connect to the shared network.
4. #### Setup credentials for included services
   Remember to set up the credentials for the included services inside shared network. All credentials are stored in the `.env` file.

### Included Services

   | Service      | Description                      | URL/Port                                             |
   |--------------|----------------------------------|------------------------------------------------------|
   | **RabbitMq** | A shared RabbitMQ message broker | Accessible via `rabbitmq:5672` on the shared network |


### Notes
   - Ensure all your local projects use the same shared network name (`common-resources-network`) for consistent connectivity.
  - Services provided here are for development purposes only and should not be used in production.