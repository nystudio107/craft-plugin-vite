TAG?=14-alpine
CONTAINER?=craft-plugin-vite-terser
DOCKERRUN=docker container run \
	--name ${CONTAINER} \
	--rm \
	-t \
	-v `pwd`:/app \
	${CONTAINER}:${TAG}

.PHONY: docker build install update npm

docker:
	docker build \
		. \
		-t ${CONTAINER}:${TAG} \
		--build-arg TAG=${TAG} \
		--no-cache
build: docker install update
	${DOCKERRUN} \
		run build
install: docker
	${DOCKERRUN} \
		install
update: docker
	${DOCKERRUN} \
		update
npm: docker
	${DOCKERRUN} \
		$(filter-out $@,$(MAKECMDGOALS))
%:
	@:
# ref: https://stackoverflow.com/questions/6273608/how-to-pass-argument-to-makefile-from-command-line
