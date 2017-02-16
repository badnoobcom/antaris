# antaris
Antaris is the prototype for an application server, written in PHP, using apache2 + mod_jk (AJPv13).
The core of this project is the adaption of the binary AJPv13 protocol in PHP.

## How to run antaris?
Make sure you have Docker installed on your system. Go to the project's src folder and run the `runInDocker.sh` shell file. A new Docker image will be built for you (takes some time, be patient). After the image is built, antaris will start up. For the next run, if the Docker image is already in place, this step will be skipped, of course.
If, for any reason, antaris doesn't start, or you made changes to the Docker config, run `runInDocker.sh force`. This will force the script to build a new image.

## Demo
Try out our demo at http://www.badnoob.com:2002
