# System Requirements and Installation Instructions

TODO: Write this section.

## System Requirements

Here are the system requirements for running the FairGrade AI software:

**Minimum Hardware Requirements:**
- Processor: Intel Core i5 or equivalent
- RAM: 8GB or higher
- Storage: At least 128GB of available space

**Recommended Hardware Requirements:**
- Processor: Intel Core i7 or equivalent
- RAM: 16GB or higher
- Storage: Solid State Drive (SSD) with at least 256GB of available space

Note: a GPU is not required.

**Minimum Software Requirements:**
- Linux Server: Ubuntu 20.04 LTS or CentOS 8
- MariaDB 10.5 or newer
- RabbitMQ 3.12 or newer
- Apache 2.4 or newer
- PHP 8.2 or newer compiled with ZTS support
- PHP Extensions: parallel, uv

**API Requirements:**
- Discord Developer Account: Users should have a Discord Developer account to access the necessary APIs and create a bot for seamless integration into Discord servers.
- OpenAI Developer Account: Users will need an OpenAI Developer account to utilize the OpenAI GPT-3 language model API for natural language processing tasks.

## Installation Instructions

Run the following command to install the FairGrade AI software:

```bash
curl -s https://raw.githubusercontent.com/fairgrade/ai/master/install/install.sh | sh
```
