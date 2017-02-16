#!/bin/bash
clear

build=false
remove=false

if [[ $1 == 'force' || $(docker images | grep img_antaris) == '' ]]; then
	echo "Building docker image ..."
	docker rmi --force img_antaris &> /dev/null
	docker build -t img_antaris . > ./dockerbuild.log
fi

echo "Running docker container and starting antaris..."

docker rm -f app_antaris &> /dev/null
SOURCEPATH=`pwd`"/src"
port=$(cat Dockerfile | grep EXPOSE | awk '{ print $2 }')
echo Antaris port: ${port}
echo connect to antaris using "http://localhost:$port"
docker run -v ${SOURCEPATH}:/www/antaris -d --name app_antaris -p ${port}:${port} img_antaris
docker attach --sig-proxy=false app_antaris
